# Benchmarks

The engine ships with a dependency-free micro-benchmark that exercises each phase in
isolation (parse, build, validate, execute) plus list throughput and DataLoader
batching:

```bash
php benchmarks/run.php   # or: composer bench
```

It reports the **median** over many iterations (more stable than the mean under GC
jitter) and verifies the DataLoader coalesces N loads into a single batch.

## Reference numbers

Indicative results on an Apple Silicon laptop, PHP 8.4 (no JIT). Numbers are
machine-specific — run the suite on your own hardware for absolute figures; the
*shape* (per-phase cost, scaling) is what matters.

| Scenario | Median | Throughput |
|---|---|---|
| parse: small query | ~6 µs | ~165k/s |
| parse: nested query | ~25 µs | ~40k/s |
| build: schema from SDL | ~100 µs | ~10k/s |
| validate: nested query | ~7 µs | ~142k/s |
| execute: flat field | ~6 µs | ~171k/s |
| execute: list of 100 | ~0.64 ms | ~1,560/s |
| execute: list of 1000 | ~6.4 ms | ~157/s |
| execute: 500 nested + DataLoader | ~7.8 ms | ~130/s |
| full: parse+validate+execute (100) | ~0.68 ms | ~1,470/s |

**Takeaways**

- Parse, validate and small executions are in the **microsecond** range — the engine
  is not a bottleneck for typical requests.
- A realistic page (10–100 objects) resolves in **single-digit milliseconds**.
- The DataLoader turns an N+1 relation into **one** batched load (verified by the
  harness), which is the difference that matters against a real database.

## Scaling & the executor rewrite

Two rounds of profiling fixed list execution:

1. **O(N²) microtask drain.** `SyncPromise::runQueue()` drained its queue with
   `array_shift()`, which re-indexes the whole array on every call. A moving index
   (plus dropping an `O(n log n)` `ksort` in `SyncPromise::all()`) restored linear
   queue draining.
2. **Per-field promise allocation.** The executor allocated a promise + closure +
   microtask for *every* field, even fully synchronous ones. It now completes
   synchronous fields/lists/objects **inline** (returning plain values) and only
   allocates promises when a resolver actually defers (DataLoader). This is the
   graphql-js hybrid model.

Result: per-item cost is now **constant (~3.8 µs/item)** instead of growing with list
size, a 1 000-item list dropped from ~56 ms to **~6.4 ms**, and peak memory for the
benchmark fell from ~56 MB to ~16 MB. DataLoader batching is unchanged — deferred
resolvers still take the async path and coalesce into one load.

## Versus webonyx/graphql-php

`webonyx/graphql-php` is the engine behind both Lighthouse and rebing/graphql-laravel,
so an engine-to-engine comparison is the fair way to read "vs Lighthouse" — it isolates
the executor from the Laravel HTTP, directive and Eloquent layers a full Lighthouse
request adds. Run it with:

```bash
composer require --dev webonyx/graphql-php
php benchmarks/vs-webonyx.php   # or: composer bench:vs
```

Indicative results (Apple Silicon, PHP 8.4, identical SDL + in-memory data):

| Scenario | this package | webonyx | verdict |
|---|---|---|---|
| parse: list query | ~21 µs | ~33 µs | **1.6× faster** |
| validate: list query | ~6 µs | ~208 µs | **~34× faster** |
| execute: flat field | ~2 µs | ~94 µs | **~41× faster** |
| execute: list of 100 | ~0.65 ms | ~1.4 ms | **2.1× faster** |
| execute: list of 1000 | ~6.4 ms | ~11.5 ms | **1.8× faster** |

**Honest reading:**

- After the executor rewrite (see above), this engine is **faster across every
  scenario** measured — dramatically so on fixed-overhead work (parse/validate/small
  execution) and comfortably on large lists too.
- Both engines batch with a DataLoader; the difference here is raw execution, resolving
  the identical query from the identical in-memory data.
- Caveat: validation cost depends on rule coverage; webonyx runs a larger standard rule
  set, so part of that gap reflects breadth, not just speed.

## Versus Lighthouse (end-to-end)

The engine numbers above isolate the executor. This measures the **full stack** —
Laravel + the `@all` directive + Eloquent over the same sqlite table — through each
package's GraphQL execution service (everything an HTTP request does bar the identical
kernel/routing overhead):

```bash
composer require --dev nuwave/lighthouse
./vendor/bin/phpunit tests/Benchmark/LighthouseEndToEndBench.php
```

`{ users { id name email } }`, 200 rows, sqlite (Apple Silicon, PHP 8.4):

| | median | ratio |
|---|---|---|
| laravel-graphql | ~3.3 ms | **1.5× faster** |
| nuwave/lighthouse | ~5.0 ms | — |

**Honest reading:** end-to-end — full Laravel, the `@all` directive and Eloquent over
the same sqlite table — this package resolves the query ~1.5× faster than Lighthouse.
The shared DB/Eloquent cost is fixed for both, so the win comes from the lower engine
overhead. Both resolve the identical query from the identical model — the difference is
engine, not features.

> Benchmarks are a regression guard, not a marketing number. If you change the
> executor or promise machinery, run `composer bench` before and after.

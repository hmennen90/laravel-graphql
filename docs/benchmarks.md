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
| execute: list of 100 | ~2.3 ms | ~440/s |
| execute: list of 1000 | ~56 ms | ~18/s |
| execute: 500 nested + DataLoader | ~28 ms | ~36/s |
| full: parse+validate+execute (100) | ~2.4 ms | ~410/s |

**Takeaways**

- Parse, validate and small executions are in the **microsecond** range — the engine
  is not a bottleneck for typical requests.
- A realistic page (10–100 objects) resolves in **single-digit milliseconds**.
- The DataLoader turns an N+1 relation into **one** batched load (verified by the
  harness), which is the difference that matters against a real database.

## Scaling & the O(N²) fix

Profiling large lists surfaced a genuine quadratic: `SyncPromise::runQueue()` drained
its microtask queue with `array_shift()`, which re-indexes the whole array on every
call — O(N²) over N microtasks. Replacing it with a moving index (plus dropping an
`O(n log n)` `ksort` in `SyncPromise::all()`) cut a 1 000-item list from ~95 ms to
~56 ms and restored near-linear scaling.

The remaining mild super-linearity at very large list sizes (thousands of objects) is
PHP's cycle collector walking the transient promise graph — a known trait of
promise-based executors. It is negligible at realistic page sizes; if you page results
(which `@paginate` encourages) you never hit it.

> Benchmarks are a regression guard, not a marketing number. If you change the
> executor or promise machinery, run `composer bench` before and after.

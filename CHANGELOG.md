## [1.7.0](https://github.com/hmennen90/laravel-graphql/compare/v1.6.0...v1.7.0) (2026-07-13)

### Features

* **bench:** real measured webonyx comparison in the dashboard ([#2](https://github.com/hmennen90/laravel-graphql/issues/2)) ([ccbd341](https://github.com/hmennen90/laravel-graphql/commit/ccbd341c385952bc46bd182a88f9260368104d17))

## [1.6.0](https://github.com/hmennen90/laravel-graphql/compare/v1.5.0...v1.6.0) (2026-07-13)

### Features

* **docs:** live PHP-WASM playground + benchmark dashboard on Pages ([#1](https://github.com/hmennen90/laravel-graphql/issues/1)) ([6192acf](https://github.com/hmennen90/laravel-graphql/commit/6192acf54052949e01ea59c3514d882f52cac62f))

## [1.5.0](https://github.com/hmennen90/laravel-graphql/compare/v1.4.0...v1.5.0) (2026-07-10)

### Features

* **code-first:** argument support for attribute fields ([f3df8b7](https://github.com/hmennen90/laravel-graphql/commit/f3df8b74004d73e078c785dce13e640318edc759))
* **federation:** derive @key/@shareable/@external/@requires/[@provides](https://github.com/provides) from SDL ([a9e3212](https://github.com/hmennen90/laravel-graphql/commit/a9e32121f666c26d480999467f3433fa294e30b0))
* **security:** disable-introspection + persisted-queries-only modes ([acada35](https://github.com/hmennen90/laravel-graphql/commit/acada35a88926da9784b01a047bef9a6d55a5680))
* **subscriptions:** ship RedisEventStream for SSE live streaming ([0e74e2a](https://github.com/hmennen90/laravel-graphql/commit/0e74e2aef966ab41d1a319d76f6bd5af55fa9fb7))

## [1.4.0](https://github.com/hmennen90/laravel-graphql/compare/v1.3.0...v1.4.0) (2026-07-10)

### Features

* **subscriptions:** Server-Sent Events (graphql-sse) transport ([de868f1](https://github.com/hmennen90/laravel-graphql/commit/de868f157ce4a17877b903532ba3522635fc9cc9))

### Bug Fixes

* **engine:** apply [@deprecated](https://github.com/deprecated) on SDL fields and enum values; add spec conformance suite ([b283602](https://github.com/hmennen90/laravel-graphql/commit/b28360291bc2e5eb26f819a3b57d81268becae25))

## [1.3.0](https://github.com/hmennen90/laravel-graphql/compare/v1.2.1...v1.3.0) (2026-07-10)

### Features

* **console:** graphql:lint — scan SDL for unsupported directives ([7fa373d](https://github.com/hmennen90/laravel-graphql/commit/7fa373d83e06f0eaf7e40d40c6f05748d156ba82))
* **directives:** [@force](https://github.com/force)Delete and [@restore](https://github.com/restore) (soft deletes) ([e811f08](https://github.com/hmennen90/laravel-graphql/commit/e811f08ebd06bad8f3fa5a7cb5deaf758e6851dd))
* **directives:** argument query-builder directives (@eq/@in/@like/@scope/[@limit](https://github.com/limit)…) ([1daec8f](https://github.com/hmennen90/laravel-graphql/commit/1daec8f838480ff1f994c80917f343e86b790b09))
* **schema:** convention-based resolution for root query/mutation fields ([1fde77b](https://github.com/hmennen90/laravel-graphql/commit/1fde77b01df33c994711319aaa5f1dba2e6cf6a5))
* **testing:** MakesGraphQLRequests trait (Lighthouse-compatible test API) ([23f796c](https://github.com/hmennen90/laravel-graphql/commit/23f796cc06ebe3561b3bce9dd47c12ce23e3fc68))

## [1.2.1](https://github.com/hmennen90/laravel-graphql/compare/v1.2.0...v1.2.1) (2026-07-10)

### Performance Improvements

* **executor:** complete synchronous fields inline (hybrid sync/async) ([82dc8d6](https://github.com/hmennen90/laravel-graphql/commit/82dc8d62368ba8f3c1019e1db96f1a897a151482))

## [1.2.0](https://github.com/hmennen90/laravel-graphql/compare/v1.1.0...v1.2.0) (2026-07-10)

### Features

* **console:** graphql:validate, graphql:print --write, make:graphql-type/directive ([d083edd](https://github.com/hmennen90/laravel-graphql/commit/d083edd48ece404d6515a86e15df256511cd700f))
* **console:** schema cache (graphql:cache/clear) + scalar/query/mutation generators ([b03d437](https://github.com/hmennen90/laravel-graphql/commit/b03d4377f295b30da53199904ee1247e9b7cfe10))

## [1.1.0](https://github.com/hmennen90/laravel-graphql/compare/v1.0.0...v1.1.0) (2026-07-10)

### Features

* **federation:** Apollo Federation v2 subgraph SDL ([d94875c](https://github.com/hmennen90/laravel-graphql/commit/d94875c2cadb2f7c2d9aaaaa1fa854e3a6e008b8))

### Performance Improvements

* **engine:** fix O(N²) microtask drain + add benchmark suite ([bc39c7c](https://github.com/hmennen90/laravel-graphql/commit/bc39c7c0f0411b562c946bdcba4ea0dd5b502663))

## 1.0.0 (2026-07-10)

### Features

* [@cache](https://github.com/cache)Control HTTP caching + graphql-ws Swoole server & Redis bridge ([69a581c](https://github.com/hmennen90/laravel-graphql/commit/69a581ca47e04dcdce64a473eb53c2e52099b6de))
* **directives:** [@order](https://github.com/order)By + [@where](https://github.com/where)Conditions filtering/sorting (M13) ([0c6b852](https://github.com/hmennen90/laravel-graphql/commit/0c6b852d3f584a56d097a83b4dc83ac8db1d64e5))
* **directives:** [@paginate](https://github.com/paginate) — paginator & Relay connection (M11) ([c19a16e](https://github.com/hmennen90/laravel-graphql/commit/c19a16e6baf79346d9917390eb78c76e6bd9fc81))
* **directives:** [@search](https://github.com/search) via Laravel Scout (M16) ([85f5453](https://github.com/hmennen90/laravel-graphql/commit/85f5453496b28bebd3c51273b554865bcf1b5f77))
* **directives:** @guard/@inject/@field/[@rename](https://github.com/rename) (M15) ([6f02812](https://github.com/hmennen90/laravel-graphql/commit/6f02812e4d03bb11c1de7862093743d54e17ce3b))
* **directives:** argument sanitisers, validation & code-first attribute equivalents ([d240b61](https://github.com/hmennen90/laravel-graphql/commit/d240b6110d7169f2a27685d358a77c1c7b585ffb))
* **directives:** build-time directive pipeline + Eloquent foundation (M9) ([a34ea10](https://github.com/hmennen90/laravel-graphql/commit/a34ea108380b258f4c699565712d81eb1dd58f4f))
* **directives:** mutations @create/@update/@delete/[@upsert](https://github.com/upsert) (M14) ([3a3cd33](https://github.com/hmennen90/laravel-graphql/commit/3a3cd33c20808e2f5bf5b728da4b524e8d053c6b))
* **directives:** nested mutations for @create/[@update](https://github.com/update) (M14 complete) ([5c9ed1c](https://github.com/hmennen90/laravel-graphql/commit/5c9ed1c6682cc608efef4cfa2dc05ad9bc7f71fb))
* **directives:** read directives @all/@find/[@first](https://github.com/first) + registry (M10) ([6e93d6c](https://github.com/hmennen90/laravel-graphql/commit/6e93d6c800c0ddf9100d1dbe9cccec5f3c9f33ba))
* **directives:** relation directives + [@count](https://github.com/count) (M12) ([a6226b7](https://github.com/hmennen90/laravel-graphql/commit/a6226b7e3f75b1ac86f613872e2f69f0acf2a167))
* **engine:** [@specified](https://github.com/specified)By + [@one](https://github.com/one)Of; docs for milestone 3 ([cb30361](https://github.com/hmennen90/laravel-graphql/commit/cb303617a042eebf96eeef8099888d1e1d076aa5))
* **engine:** AST nodes + recursive-descent parser (queries + SDL) ([4bebd45](https://github.com/hmennen90/laravel-graphql/commit/4bebd4576a67ba91cf110aecb434db75913e7c47))
* **engine:** attribute-based code-first schema ([5c62444](https://github.com/hmennen90/laravel-graphql/commit/5c62444036b42ef63e3fcc402d5fa277e9ed7102))
* **engine:** custom executable directives ([9933731](https://github.com/hmennen90/laravel-graphql/commit/9933731072946568eb7b798a95df19356bee865c))
* **engine:** deferred executor + DataLoader (N+1 batching) ([4c4c212](https://github.com/hmennen90/laravel-graphql/commit/4c4c212eb4c74e41121a95ac5d8b2cb404444450))
* **engine:** document validator (core rules) ([03ea86c](https://github.com/hmennen90/laravel-graphql/commit/03ea86ca43d613f124c39a9fc47a424f0e87cccd))
* **engine:** error base, source locations, execution result ([4d1b857](https://github.com/hmennen90/laravel-graphql/commit/4d1b85792bde0042e8aa83dec6c2b9b2866c69ab))
* **engine:** executor + introspection ([9869e2e](https://github.com/hmennen90/laravel-graphql/commit/9869e2ea98edb1b20819badca684d2696bf9d4a9))
* **engine:** introspection completeness (milestone 4) ([d2de4ef](https://github.com/hmennen90/laravel-graphql/commit/d2de4ef525611007ef886455ae837ae33fbacb3c))
* **engine:** lexer with full token set, escapes, block strings ([40a037e](https://github.com/hmennen90/laravel-graphql/commit/40a037ec55dcac4119493614125f7f3c4204e429))
* **engine:** query depth & complexity limits + docs ([f09634b](https://github.com/hmennen90/laravel-graphql/commit/f09634b9b5ed579e6f1c824b7e1572bad7f4c1e8))
* **engine:** schema builders (code-first + SDL funnel) ([7524c6a](https://github.com/hmennen90/laravel-graphql/commit/7524c6ac3b2a107bc25345aa8a1abd1afd898ae1))
* **engine:** schema self-validation ([9f65314](https://github.com/hmennen90/laravel-graphql/commit/9f6531402abe751463f6e0b2b000bed2da91c115))
* **engine:** SDL schema printer; graphql:print outputs SDL; docs ([5a4dc77](https://github.com/hmennen90/laravel-graphql/commit/5a4dc77ecd36c852fa461e6ade129e645bd740d8))
* **engine:** SDL type extensions (extend type/interface/input) ([3c53be3](https://github.com/hmennen90/laravel-graphql/commit/3c53be3735025c590e8171f02ae844cd5839cf26))
* **engine:** type system + schema (scalars, composites, registry) ([a7de42a](https://github.com/hmennen90/laravel-graphql/commit/a7de42a6c42c8b9ced3419a1d89d081adf70d805))
* **federation:** Apollo Federation subgraph support (M16) ([178fb6f](https://github.com/hmennen90/laravel-graphql/commit/178fb6f4bf955bd1ab7bc462948a5bf8f39d502a))
* **generation:** schema types from models, FormRequests & JSON responses ([7003a73](https://github.com/hmennen90/laravel-graphql/commit/7003a733d89b7f9e0744ad65d16d9f57e33b2d37))
* Laravel 13 support ([d8d7fe2](https://github.com/hmennen90/laravel-graphql/commit/d8d7fe2ebfcb20c4b9919ec9b20b85887d21808a))
* **laravel:** file uploads, [@can](https://github.com/can) auth directive + docs (milestone 7) ([a0f525c](https://github.com/hmennen90/laravel-graphql/commit/a0f525c0456f3de5bf47e07b6f6278ba5228b47d))
* **laravel:** service provider, endpoint, errors, auth, graphiql ([70d83cc](https://github.com/hmennen90/laravel-graphql/commit/70d83cc8346d0afb5fa931d4949ab38eb5ba8dfb))
* Relay pagination helpers + Automatic Persisted Queries ([028ea13](https://github.com/hmennen90/laravel-graphql/commit/028ea13514b16de6bbd2b9780c6a665d5c0053ae))
* **subscriptions:** broadcasting-based transport (milestone 2) ([4509b09](https://github.com/hmennen90/laravel-graphql/commit/4509b098da383ebb7fc2d42f06809cb4f931ffd4))
* **subscriptions:** graphql-ws protocol handler (milestone 6) ([69136b6](https://github.com/hmennen90/laravel-graphql/commit/69136b68e0b1d57d59cf596a54386cfe662c9362))
* **validation:** comprehensive spec rule set ([5c79d12](https://github.com/hmennen90/laravel-graphql/commit/5c79d12de914f71f6e6d2d542527df35fe1fbf64))
* **validation:** UniqueInputFieldNames + OverlappingFieldsCanBeMerged ([853a4d5](https://github.com/hmennen90/laravel-graphql/commit/853a4d5e912b18e5cc592967ace2f6ece0d9ff78))

### Bug Fixes

* **ci:** valid dry_run input name in release workflow ([f9e1d0d](https://github.com/hmennen90/laravel-graphql/commit/f9e1d0dd759070b9b6c2fdca16e5224c4d7ce39e))

## [1.1.0](https://github.com/hmennen90/laravel-graphql/compare/v1.0.0...v1.1.0) (2026-07-10)

### Features

* Laravel 13 support ([d8d7fe2](https://github.com/hmennen90/laravel-graphql/commit/d8d7fe2ebfcb20c4b9919ec9b20b85887d21808a))

## 1.0.0 (2026-07-10)

### Features

* [@cache](https://github.com/cache)Control HTTP caching + graphql-ws Swoole server & Redis bridge ([69a581c](https://github.com/hmennen90/laravel-graphql/commit/69a581ca47e04dcdce64a473eb53c2e52099b6de))
* **engine:** [@specified](https://github.com/specified)By + [@one](https://github.com/one)Of; docs for milestone 3 ([cb30361](https://github.com/hmennen90/laravel-graphql/commit/cb303617a042eebf96eeef8099888d1e1d076aa5))
* **engine:** AST nodes + recursive-descent parser (queries + SDL) ([4bebd45](https://github.com/hmennen90/laravel-graphql/commit/4bebd4576a67ba91cf110aecb434db75913e7c47))
* **engine:** attribute-based code-first schema ([5c62444](https://github.com/hmennen90/laravel-graphql/commit/5c62444036b42ef63e3fcc402d5fa277e9ed7102))
* **engine:** custom executable directives ([9933731](https://github.com/hmennen90/laravel-graphql/commit/9933731072946568eb7b798a95df19356bee865c))
* **engine:** deferred executor + DataLoader (N+1 batching) ([4c4c212](https://github.com/hmennen90/laravel-graphql/commit/4c4c212eb4c74e41121a95ac5d8b2cb404444450))
* **engine:** document validator (core rules) ([03ea86c](https://github.com/hmennen90/laravel-graphql/commit/03ea86ca43d613f124c39a9fc47a424f0e87cccd))
* **engine:** error base, source locations, execution result ([4d1b857](https://github.com/hmennen90/laravel-graphql/commit/4d1b85792bde0042e8aa83dec6c2b9b2866c69ab))
* **engine:** executor + introspection ([9869e2e](https://github.com/hmennen90/laravel-graphql/commit/9869e2ea98edb1b20819badca684d2696bf9d4a9))
* **engine:** introspection completeness (milestone 4) ([d2de4ef](https://github.com/hmennen90/laravel-graphql/commit/d2de4ef525611007ef886455ae837ae33fbacb3c))
* **engine:** lexer with full token set, escapes, block strings ([40a037e](https://github.com/hmennen90/laravel-graphql/commit/40a037ec55dcac4119493614125f7f3c4204e429))
* **engine:** query depth & complexity limits + docs ([f09634b](https://github.com/hmennen90/laravel-graphql/commit/f09634b9b5ed579e6f1c824b7e1572bad7f4c1e8))
* **engine:** schema builders (code-first + SDL funnel) ([7524c6a](https://github.com/hmennen90/laravel-graphql/commit/7524c6ac3b2a107bc25345aa8a1abd1afd898ae1))
* **engine:** schema self-validation ([9f65314](https://github.com/hmennen90/laravel-graphql/commit/9f6531402abe751463f6e0b2b000bed2da91c115))
* **engine:** SDL type extensions (extend type/interface/input) ([3c53be3](https://github.com/hmennen90/laravel-graphql/commit/3c53be3735025c590e8171f02ae844cd5839cf26))
* **engine:** type system + schema (scalars, composites, registry) ([a7de42a](https://github.com/hmennen90/laravel-graphql/commit/a7de42a6c42c8b9ced3419a1d89d081adf70d805))
* **generation:** schema types from models, FormRequests & JSON responses ([7003a73](https://github.com/hmennen90/laravel-graphql/commit/7003a733d89b7f9e0744ad65d16d9f57e33b2d37))
* **laravel:** file uploads, [@can](https://github.com/can) auth directive + docs (milestone 7) ([a0f525c](https://github.com/hmennen90/laravel-graphql/commit/a0f525c0456f3de5bf47e07b6f6278ba5228b47d))
* **laravel:** service provider, endpoint, errors, auth, graphiql ([70d83cc](https://github.com/hmennen90/laravel-graphql/commit/70d83cc8346d0afb5fa931d4949ab38eb5ba8dfb))
* Relay pagination helpers + Automatic Persisted Queries ([028ea13](https://github.com/hmennen90/laravel-graphql/commit/028ea13514b16de6bbd2b9780c6a665d5c0053ae))
* **subscriptions:** broadcasting-based transport (milestone 2) ([4509b09](https://github.com/hmennen90/laravel-graphql/commit/4509b098da383ebb7fc2d42f06809cb4f931ffd4))
* **subscriptions:** graphql-ws protocol handler (milestone 6) ([69136b6](https://github.com/hmennen90/laravel-graphql/commit/69136b68e0b1d57d59cf596a54386cfe662c9362))
* **validation:** comprehensive spec rule set ([5c79d12](https://github.com/hmennen90/laravel-graphql/commit/5c79d12de914f71f6e6d2d542527df35fe1fbf64))
* **validation:** UniqueInputFieldNames + OverlappingFieldsCanBeMerged ([853a4d5](https://github.com/hmennen90/laravel-graphql/commit/853a4d5e912b18e5cc592967ace2f6ece0d9ff78))

### Bug Fixes

* **ci:** valid dry_run input name in release workflow ([f9e1d0d](https://github.com/hmennen90/laravel-graphql/commit/f9e1d0dd759070b9b6c2fdca16e5224c4d7ce39e))

# Changelog

All notable changes to this project are documented in this file.

This file is generated automatically by
[semantic-release](https://github.com/semantic-release/semantic-release) from
[Conventional Commits](https://www.conventionalcommits.org/); the format follows
[Keep a Changelog](https://keepachangelog.com/) and the project adheres to
[Semantic Versioning](https://semver.org/).

<!-- semantic-release will insert released versions below. -->

## [Unreleased]

Initial development — no versioned release has been cut yet. Run the **Release**
workflow (manual dispatch) to publish the first version.

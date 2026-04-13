# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**hegel-php** is a PHP 8.4+ client library for the [Hegel](https://hegel.dev) property-based testing protocol. It communicates with a `hegel-core` Python server over stdio using a binary wire protocol with CBOR-encoded payloads, and integrates with PHPUnit 12 via a trait.

## Commands

```bash
# Install dependencies
composer install

# Run all tests (94 tests)
./vendor/bin/phpunit

# Run only unit tests (84 tests, no external dependencies)
./vendor/bin/phpunit --testsuite unit

# Run only integration tests (10 tests, requires uv for hegel-core server)
./vendor/bin/phpunit --testsuite integration

# Run a single test file
./vendor/bin/phpunit tests/Unit/Wire/PacketTest.php

# Run a single test method
./vendor/bin/phpunit --filter test_roundtrip_basic tests/Unit/Wire/PacketTest.php

# Static analysis
mago lint

# Formatting
mago format
```

Integration tests require `uv` (Python package manager) to be installed. The server command can be overridden via `HEGEL_SERVER_COMMAND` env var.

## Architecture

### Layer Stack

```
PHPUnit Test → HegelTrait::invokeTestMethod() → Runner → Connection → Stream → Wire (Packet/Reader/Writer) → ServerProcess (stdio)
                                                              ↕
                                                          CborCodec
```

### Key Layers

**Wire** (`src/Hegel/Wire/`) — Binary packet format: 20-byte header (magic `0x4845474C`, CRC32, streamId, messageId, payloadLen) + payload + `0x0A` terminator. The reply bit is bit 31 of the messageId field. Close-stream uses messageId `0x7FFFFFFF` with payload `0xFE`.

**Protocol** (`src/Hegel/Protocol/`) — `Connection` manages bidirectional multiplexed streams over stdin/stdout. Client stream IDs are odd (`(counter << 1) | 1` → 3, 5, 7...), server IDs are even. `Stream` handles request-reply sequencing with async packet buffering for out-of-order messages.

**Codec** (`src/Hegel/Codec/`) — Wraps `spomky-labs/cbor-php`. Has custom `normalize()` to work around the library returning strings for integer values, and handles Tag 91 (Hegel-tagged strings).

**Generator** (`src/Hegel/Generator/`) — Composable value generators with `map()`, `filter()`, `flatMap()`. The `Generators` factory class provides constructors (`integers`, `text`, `lists`, `oneOf`, etc.). Generators produce schemas sent to the server; values come back over the wire.

**Runner** (`src/Hegel/`) — `Runner::run()` sends a `run_test` command, then event-loops on `test_case`/`test_done` events. Each test case runs on a dedicated stream. Reports status as VALID/INVALID/INTERESTING. `TestCase` is passed to user test functions for `draw()`, `assume()`, `note()`, and `target()`.

**Server** (`src/Hegel/Server/`) — `ServerProcess` launches `hegel-core==0.4.0` via `uv tool run`. `Session` is a global singleton providing lazy server lifecycle.

**PHPUnit** (`src/Hegel/PHPUnit/`) — `HegelTrait` overrides `invokeTestMethod()` (new in PHPUnit 13) to intercept methods annotated with `#[Property]`. The Hegel `TestCase` is automatically injected as the first argument; DataProvider/TestWith arguments follow after it.

### Protocol Details

- Handshake is raw ASCII (`hegel_handshake_start` → `Hegel/0.10`), everything after is CBOR
- All replies to the server **must** use `{"result": value}` CBOR map format (not bare values) — the Python server's `get()` does `if "error" in payload` which crashes on `None`
- Protocol version: `Hegel/0.10`, server version: `hegel-core==0.4.0`

## cbor-php Library Pitfalls

- `UnsignedIntegerObject::normalize()` and `NegativeIntegerObject::normalize()` return **strings**, not ints — `CborCodec::normalize()` casts them back
- Tag 91 arrives as `GenericTag` — must extract inner value
- Collections need recursive normalization

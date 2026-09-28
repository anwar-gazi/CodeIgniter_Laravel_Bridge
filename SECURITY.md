# Security policy

## Supported runtime

This package pins Laravel Framework 8.83.29 because Laravel 8 is the final
Laravel major compatible with PHP 7.3. Both PHP 7.3 and Laravel 8 are upstream
end-of-life. Production users must treat this bridge as a bounded migration
runtime, apply operating-system security updates, and plan a PHP upgrade.

Laravel 8.83.29 uses SwiftMailer 6.3, which Composer marks as abandoned. The
email-address patch in this package hardens the relevant Laravel wrapper, but it
does not make SwiftMailer maintained again. Keep mail transport access narrow,
validate all user-controlled addresses, and prioritize the eventual PHP and
Laravel upgrade to a Symfony Mailer-based release. Composer is configured to
report this abandonment without failing the audit; advisory failures remain
enabled.

## Laravel 8 advisory handling

Composer is permitted to resolve the PHP 7.3 dependency set for exactly six advisory
IDs. The exceptions are documented in both the current `policy` format and the
legacy `audit` format used by Composer 2.9 and earlier; global advisory blocking
remains enabled.

- `PKSA-3r5d-mb8f-1qw9` / `GHSA-5vg9-5847-vvmq`: the included Composer patch
  rejects CR and LF characters in Laravel's default email validation and at
  every `Illuminate\Mail\Message` address entry point. This adapts the upstream
  defense to Laravel 8's SwiftMailer implementation.
- `PKSA-8qx3-n5y5-vvnd` / `GHSA-78fx-h6xr-vch4`: the vulnerable
  `Illuminate\Validation\Rules\File` implementation is absent from Laravel
  8.83.29. Laravel 8's built-in string file rules do not execute that code path.
- `PKSA-m5cs-t1y6-qpcs` / `GHSA-crmm-hgp2-wgrp`: Laravel 8.83.29 has no local
  filesystem temporary signed URL/upload implementation. Its filesystem
  adapter supports temporary URLs through S3, driver methods, or an explicitly
  application-provided callback, so the reported local-driver path-confusion
  code path is absent.
- `PKSA-fndg-qryc-dyc9` / `GHSA-c2pc-g5qf-rfrf`,
  `PKSA-9q1p-3s19-bp1q` / `GHSA-j8pm-gj4c-rq4x`, and
  `PKSA-t21r-vtr5-3mdz` / `GHSA-2q4p-g7hv-5rgv`: Laravel's supported
  CommonMark releases with the upstream algorithmic fixes require PHP 7.4 or
  newer. CommonMark 1.4.3 is pinned because it supports PHP 7.3 and predates the
  later extension-specific advisory ranges. The included patch applies the
  advisory's documented input-size workaround at the core parser: Markdown is
  limited to 256 KiB total and 2 KiB per line, including content modified by a
  pre-parse listener.

The test suite verifies both patches and the two non-applicability assumptions.
Reassess all exceptions before changing either pinned dependency or adding
later-version filesystem or validation implementations.

## Reporting

Report security issues privately to the package maintainer. Do not publish an
unpatched exploit in a public issue.

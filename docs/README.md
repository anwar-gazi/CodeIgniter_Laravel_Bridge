# Developer documentation

The native Laravel bridge is the current runtime. The older CI-side
compatibility helpers are documented separately so the two execution models are
not confused.

## Reading paths

### First-time integrator

1. [Architecture](architecture.md)
2. [Installation](installation.md)
3. [Configuration reference](configuration.md)
4. [Route migration guide](migration-guide.md)
5. [Deployment and operations](deployment.md)

### Package contributor

1. [Architecture](architecture.md)
2. [Development guide](development.md)
3. [Security policy](../SECURITY.md)
4. [Changelog](../CHANGELOG.md)

### On-call developer

1. [Troubleshooting](troubleshooting.md)
2. [Configuration reference](configuration.md)
3. [Deployment and operations](deployment.md)

### Maintainer of a v1-era integration

1. [Legacy compatibility API](legacy-compatibility-api.md)
2. [Route migration guide](migration-guide.md)

## Terminology

- **Host application:** the Laravel 8 application that installs this package.
- **Legacy application:** the existing CodeIgniter 3 application.
- **Dispatcher:** dependency-free PHP executed before either framework.
- **Ownership manifest:** generated PHP data containing compiled Laravel route
  matchers and a checksum.
- **Owned route:** a request whose path, host, and scheme match the manifest;
  method ownership depends on `claim_method_mismatches`.
- **Native bridge:** the v2 path that boots Laravel's actual HTTP kernel.
- **Legacy compatibility API:** v1-era Laravel-like utilities running inside CI.

gearman-async [![Latest Stable Version](https://img.shields.io/packagist/v/zikarsky/react-gearman.svg?style=flat-square)](https://packagist.org/packages/zikarsky/react-gearman) [![Total Downloads](https://img.shields.io/packagist/dt/zikarsky/react-gearman.svg?style=flat-square)](https://packagist.org/packages/zikarsky/react-gearman) 
=============
[![Build Status](https://img.shields.io/travis/bzikarsky/react-gearman.svg?style=flat-square)](https://travis-ci.org/bzikarsky/react-gearman)

A async Gearman implementation for PHP ontop of reactphp

## Current status:
- There is a working implementation for a GearmanClient
- There is a partially working implementation for GearmanWorker - will be refactored
- Code is unit-tested and adheres to http://gearman.org/protocol/ 
- No real integration tests with gearmand yet. Some examples can be found in `examples`


## Development

Your PRs and changes are very welcome! 

Please run tests and fix code-style with:

- `bin/phpunit`
- `bin/php-cs-fixer fix`

If you want to check code-style before fixing it, run `bin/php-cs-fixer fix --dry-run --diff`


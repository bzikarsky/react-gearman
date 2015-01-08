gearman-async [![Latest Stable Version](https://img.shields.io/packagist/v/zikarsky/react-gearman.svg?style=flat-square)](https://packagist.org/packages/zikarsky/react-gearman) [![Total Downloads](https://img.shields.io/packagist/dt/zikarsky/react-gearman.svg?style=flat-square)](https://packagist.org/packages/zikarsky/react-gearman) 
=============
[![Build Status](https://img.shields.io/travis/bzikarsky/react-gearman.svg?style=flat-square)](https://travis-ci.org/bzikarsky/react-gearman)
[![HHVM Status](https://img.shields.io/hhvm/zikarsky/react-gearman.svg?style=flat-square)](http://hhvm.h4cc.de/package/zikarsky/react-gearman)

A async Gearman implementation for PHP ontop of reactphp

## Current status:
- There is a working implementation for a GearmanClient
- There is a partially working implementation for GearmanWorker - will be refactored
- Code is unit-tested and adheres to http://gearman.org/protocol/ 
- No real integration tests with gearmand yet. Some examples can be found in `examples`

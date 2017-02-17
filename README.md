# idOS Widget Handler

This service handler is the oAuth layer of idOS.

# Setup

You can read how to setup the idOS Widget Handler in the [Setup Manual](Setup.md)

# Operation

You can read how to operate the idOS Widget Handler in the [Operation Manual](Operation.md)



## Documentation

To generate the internal documentation, run:


```bash
./vendor/bin/phploc --log-xml=build/phploc.xml app/
./vendor/bin/phpmd app/ xml cleancode,codesize,controversial,design,naming,unusedcode --reportfile build/pmd.xml
./vendor/bin/phpcs --standard=VeriduRuleset.xml --report=xml --report-file=build/phpcs.xml app/
./vendor/bin/phpdox --file phpdox.xml.dist
```

The files will be stored at [docs/](docs/).

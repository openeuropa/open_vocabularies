# Open Vocabularies

The Open Vocabularies module allows users to choose how a piece of content will be categorised without having to change the related content type field definition.
It does that by providing:

* An open vocabulary field type
* The possibility to create associations between open vocabulary field types and Drupal categorisation systems, such as Drupal taxonomies, Publication Office vocabularies, or subsets of them.

Once a content type is equipped with an open vocabulary field, users with appropriate access can decide which type of entities that field will able to reference, by creating associations.

# Requirements

This module unfortunately requires a patch to Drupal core 9.x releases, due to a bug that prevents placing the generated fields
programmatically in the form display.\
The patch is already included in this component composer.json. To have it installed, irst require the component needed to apply the patch:
```bash
composer require cweagans/composer-patches
```
Then add in the `extra` section of your project `composer.json`:
```json
  [...]
  "extra": {
    "composer-exit-on-patch-failure": true,
    "enable-patching": true,
    [...]
  }
  [...]
```

If you require this module in Drupal 10 you must set said patch as ignored in your composer.json.\
You can do this with the following command:
```bash
composer config --merge --json "extra.patches-ignore.openeuropa/open_vocabularies" '{"drupal/core": {"Entity display entities are incorrectly unserialized @see https://www.drupal.org/project/drupal/issues/3171333": "https://www.drupal.org/files/issues/2020-09-17/3171333-6.patch"}}'
```

## Development setup

You can build the development site by running the following steps:

* Install the Composer dependencies:

```bash
composer install
```

A post command hook (`drupal:site-setup`) is triggered automatically after `composer install`.
This will symlink the module in the proper directory within the test site and perform token substitution in test configuration files such as `behat.yml.dist`.

**Please note:** project files and directories are symlinked within the test site by using the
[OpenEuropa Task Runner's Drupal project symlink](https://github.com/openeuropa/task-runner-drupal-project-symlink) command.

If you add a new file or directory in the root of the project, you need to re-run `drupal:site-setup` in order to make
sure they are be correctly symlinked.

If you don't want to re-run a full site setup for that, you can simply run:

```
$ ./vendor/bin/run drupal:symlink-project
```

* Install test site by running:

```bash
$ ./vendor/bin/run drupal:site-install
```

The development site web root should be available in the `build` directory.

### Using Docker Compose

Alternatively, you can build a development site using [Docker](https://www.docker.com/get-docker) and
[Docker Compose](https://docs.docker.com/compose/) with the provided configuration.

Docker provides the necessary services and tools such as a web server and a database server to get the site running,
regardless of your local host configuration.

#### Requirements:

- [Docker](https://www.docker.com/get-docker)
- [Docker Compose](https://docs.docker.com/compose/)

#### Configuration

By default, Docker Compose reads two files, a `docker-compose.yml` and an optional `docker-compose.override.yml` file.
By convention, the `docker-compose.yml` contains your base configuration and it's provided by default.
The override file, as its name implies, can contain configuration overrides for existing services or entirely new
services.
If a service is defined in both files, Docker Compose merges the configurations.

Find more information on Docker Compose extension mechanism on [the official Docker Compose documentation](https://docs.docker.com/compose/extends/).

#### Usage

To start, run:

```bash
docker-compose up
```

It's advised to not daemonize `docker-compose` so you can turn it off (`CTRL+C`) quickly when you're done working.
However, if you'd like to daemonize it, you have to add the flag `-d`:

```bash
docker-compose up -d
```

Then:

```bash
docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install
```

Using default configuration, the development site files should be available in the `build` directory and the development site
should be available at: [http://127.0.0.1:8080/build](http://127.0.0.1:8080/build).

#### Running the tests

To run the grumphp checks:

```bash
docker-compose exec web ./vendor/bin/grumphp run
```

To run the phpunit tests:

```bash
docker-compose exec web ./vendor/bin/phpunit
```

To run the behat tests:

```bash
docker-compose exec web ./vendor/bin/behat
```

#### Step debugging

To enable step debugging from the command line, pass the `XDEBUG_SESSION` environment variable with any value to
the container:

```bash
docker-compose exec -e XDEBUG_SESSION=1 web <your command>
```

Please note that, starting from XDebug 3, a connection error message will be outputted in the console if the variable is
set but your client is not listening for debugging connections. The error message will cause false negatives for PHPUnit
tests.

To initiate step debugging from the browser, set the correct cookie using a browser extension or a bookmarklet
like the ones generated at https://www.jetbrains.com/phpstorm/marklets/.

## Contributing

Please read [the full documentation](https://github.com/openeuropa/openeuropa) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the available versions, see the [tags on this repository](https://github.com/openeuropa/open_vocabularies/tags).

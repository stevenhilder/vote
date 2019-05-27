# Home assignment

![screenshot](/screenshot.png)

* ### Overview
	* Back-end developed on __CentOS 7.5__ with __PHP 7.2__ (via PHP-FPM and __Nginx__)
		* Vote storage in POSIX shared memory (with atomic locking to prevent race conditions)
		* Communication between servers using __WebSockets__
	* Front-end built using [__Semantic UI__](https://semantic-ui.com/) with minimal client-side JavaScript
	* Run `make` to install the project's dependencies and start the WebSocket listener

------------------------------------------------------------------------------------

* ### Project structure:
	* [nginx.conf](/nginx.conf) - Sample virtual host configuration for Nginx
	* [vote-config.json](/vote-config.json) - JSON configuration file (supports customization of vote options and server addresses)
	* public/
		* [FrontController.php](/public/FrontController.php) - Webserver entry point, invokes VoteApplication with global request data
	* src/
		* [VoteOption.php](/src/VoteOption.php) - A simple class representing a single vote option
		* [VoteConfig.php](/src/VoteConfig.php) - Loads configuration file and provides logic for counting votes and returning results
		* [VoteApplication.php](/src/VoteApplication.app) - Deals with request/response routing and HTML rendering
		* templates/ - HTML templates for rendering responses with support for `{{ placeholders }}`
	* bin/
		* [websocket-server](/bin/websocket-server) - Invoked automatically by `make` or `make run`
		* [reset](/bin/reset) - Clears data from shared memory; invoked automatically by `make reset` or `make clean`

PHP dependencies are managed using __Composer__. The Composer PHAR installer is bundled with this project;
running `make` should install all required files.

Required PHP extensions _(all are bundled with PHP, no PECL extensions required)_:

* __ext/json__ for parsing JSON configuration file
* __ext/pcre__ regular expressions used for rendering HTML templates and validating data
* __ext/sysvsem__ & __ext/sysvshm__ System V IPC semaphores and shared memory

------------------------------------------------------------------------------------

* ### Proposed improvements:
	* __Use a database as a central source of truth for vote configuration__
		* Current solution (configuration files on each webserver) susceptible to fragmentation
		* Either relational or NoSQL could fulfill this requirement
		* Read/write master and read-only slave(s) for load balancing
	* __Use a more robust system for vote storage__
		* Redis would provide high performance, [persistence](https://redis.io/topics/persistence) and single-round-trip [atomic integer increment](https://redis.io/commands/incr)
		* Alternatively, if data of individual votes is desired _(eg. timestamp, origin IP address etc.)_, an event store such as [Kafka](https://kafka.apache.org/) could provide distributed storage of votes
	* __Use a daemon on each webserver to maintain persistent WebSocket connections between servers__
		* Current solution reconnects for every results query
		* Daemon could automatically re-establish failed connections
		* Use [WAMP](https://wamp-proto.org/) subprotocol for standardized PubSub and/or RPC communication over WebSockets or raw TCP sockets
	* __Implement client-server WebSocket communication for front-end so that results can update in real time__

<?php declare(strict_types = 1);

namespace HomeAssignment;

use Exception;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

use WebSocket\Client;

class VoteConfig {

	private $_title;
	private $_servers;
	private $_options;
	private $_sharedMemoryKey;

	public static function load(string $filename): self {

		// Check whether the config file exists
		if ($filename === '') {
			throw new Exception('Vote config filename must be non-empty');
		} elseif (!is_file($filename)) {
			throw new Exception("Vote config file not found: $filename");

		// Check whether the config file can be read by the current process
		} elseif (!is_readable($filename)) {
			throw new Exception("Vote config file is not readable: $filename");

		// Attempt to read the config file
		} elseif (($configJSON = @file_get_contents($filename)) === FALSE) {
			throw new Exception("Error reading vote config file: $filename");
		} elseif ($configJSON === '') {
			throw new Exception("Vote config file is empty: $filename");

		// Validate JSON config data
		} elseif (($config = @json_decode($configJSON)) === NULL && json_last_error() !== JSON_ERROR_NONE) {
			throw new Exception('Error decoding JSON vote config data: ' . json_last_error_msg());
		} elseif (!is_object($config)
			|| !property_exists($config, 'title')
			|| !is_string($config->title)
			|| !property_exists($config, 'options')
			|| !is_array($config->options)
			|| count(array_filter($config->options, function ($value): bool {
				return !is_string($value) || $value === '';
			})) !== 0
			|| !property_exists($config, 'servers')
			|| !is_array($config->servers)
			|| count(array_filter($config->servers, function ($value): bool {
				return !is_string($value) || preg_match('/^\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}$/', $value) !== 1;
			})) !== 0
		) {
			throw new Exception('Invalid vote config');

		// Return a new VoteConfig instance
		} else { 
			return new self($config->title, $config->servers, ...array_map(function (string $name): VoteOption {
				return new VoteOption($name);
			}, $config->options));
		}
	}

	private function __construct(string $title, array $servers, VoteOption ...$options) {
		if ($title === '') {
			throw new Exception('Vote title must be non-empty');
		} elseif (count($options) < 2) {
			throw new Exception('Vote config must contain at least 2 options');
		} elseif (($this->_sharedMemoryKey = ftok(__FILE__, 'v')) === -1) {
			throw new Exception('Unable to generate shared memory key');
		} else {
			$this->_title = $title;
			$this->_servers = $servers;
			$this->_options = $options;
		}
	}

	public function getOptions(): array {
		return $this->_options;
	}

	public function getResults(bool $combineRemoteResults = TRUE): array {
		$sharedMemorySegment = shm_attach($this->_sharedMemoryKey);
		try {

			// Get this server's results from shared memory
			$results = array_map(function (VoteOption $option) use ($sharedMemorySegment): VoteOption {
				$optionID = $option->getID();
				return shm_has_var($sharedMemorySegment, $optionID) ? new VoteOption($option->getName(), shm_get_var($sharedMemorySegment, $optionID)) : $option;
			}, $this->_options);

			if ($combineRemoteResults) {

				// Ignore the local server's IP addess
				$servers = array_filter($this->_servers, function (string $ip): bool {
					return $ip !== ($_SERVER['SERVER_ADDR'] ?? $_SERVER['SERVER_NAME']);
				});

				// Merge remote results with local results
				foreach ($servers as $ip) {
					$client = new Client("ws://$ip:9001");
					$client->send('get-results');
					$remoteResults = unserialize($client->receive());
					$results = array_map(function (VoteOption $option) use ($remoteResults): VoteOption {
						$optionID = $option->getID();
						foreach ($remoteResults as $id => $count) {
							if ($id === $optionID) {
								return new VoteOption($option->getName(), $option->getCount() + $count);
							}
						}
						return $option;
					}, $results);
				}
			}
			return $results;

		} catch (Throwable $throwable) {
			throw new Exception("Error processing results: {$throwable->getMessage()}", 500, $throwable);
		} finally {
			@shm_detach($sharedMemorySegment);
		}
	}

	public function getTitle(): string {
		return $this->_title;
	}

	public function vote(VoteOption $option): void {
		try {

			// Acquire exclusive lock for shared memory
			if (($semaphore = @sem_get($this->_sharedMemoryKey)) === FALSE) {
				throw new Exception('Unable to create semaphore');
			} elseif (!@sem_acquire($semaphore)) {
				$semaphore = FALSE;
				throw new Exception('Semaphore lock could not be acquired');
			} else {
				$sharedMemorySegment = shm_attach($this->_sharedMemoryKey);
				$optionID = $option->getID();

				// Increment the selected option
				if (shm_has_var($sharedMemorySegment, $optionID)) {
					if (($optionCount = shm_get_var($sharedMemorySegment, $optionID)) === PHP_INT_MAX) {
						throw new Exception("Vote option '{$option->getName()}' count has reached maximum");
					} else {
						++$optionCount;
					}
				} else {
					$optionCount = 1;
				}

				// Write the new value back to shared memory
				if (!@shm_put_var($sharedMemorySegment, $optionID, $optionCount)) {
					throw new Exception('Unable to write vote option count to shared memory');
				}
			}
		} catch (Throwable $throwable) {
			throw new Exception("Error processing vote: {$throwable->getMessage()}", 500, $throwable);

		// Release the exclusive lock
		} finally {
			@sem_release($semaphore);
		}
	}
}

<?php declare(strict_types = 1);

namespace HomeAssignment;

class VoteOption {

	public function __construct(string $name, int $count = 0) {
		if ($name === '') {
			throw new Exception('VoteOption name must be non-empty');
		} elseif ($count < 0) {
			throw new Exception('VoteOption initial count cannot be negative');
		} else {
			$this->_name = $name;
			$this->_count = $count;
		}
	}

	public function getCount(): int {
		return $this->_count;
	}

	// A numeric checksum of the option name, used in shared memory keys.
	public function getID(): int {
		return crc32($this->_name);
	}

	public function getName(): string {
		return $this->_name;
	}
}

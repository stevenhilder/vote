<?php declare(strict_types = 1);

namespace HomeAssignment;

class VoteApplication {

	private const CONFIG_FILENAME = __DIR__ . '/../vote-config.json';
	private const TEMPLATE_PATH = __DIR__ . '/templates/';

	// Request handler for GET /results
	private static function _handleGetResults(): int {
		$config = VoteConfig::load(self::CONFIG_FILENAME);
		$results = $config->getResults();
		$total = array_sum(array_map(function (VoteOption $result): int {
			return $result->getCount();
		}, $results));
		return self::_respondOK('Results', self::_renderTemplate('results', [
			'title' => $config->getTitle(),
			'results' => implode(array_map(function (VoteOption $option) use ($total): string {
				$name = $option->getName();
				$count = $option->getCount();
				return self::_renderTemplate('results-option', [
					'name' => $name,
					'percent' => $total === 0 ? 0 : number_format($count / $total * 100, 0),
					'color' => strtolower(explode(' ', $name, 2)[0]),
					'count' => $count,
					'plural' => $count === 1 ? '' : 's',
				]);
			}, $results)),
		]));
	}

	// Request handler for GET /vote
	private static function _handleGetVote(): int {
		$config = VoteConfig::load(self::CONFIG_FILENAME);
		return self::_respondOK($config->getTitle(), self::_renderTemplate('vote', [
			'title' => $config->getTitle(),
			'vote-options' => implode(array_map(function (VoteOption $option): string {
				return self::_renderTemplate('vote-option', [
					'id' => $option->getID(),
					'name' => $option->getName(),
				]);
			}, $config->getOptions())),
		]));
	}

	// Request handler for POST /vote
	private static function _handlePostVote(array $postData): int {
		$config = VoteConfig::load(self::CONFIG_FILENAME);
		$options = $config->getOptions();
		if (array_key_exists('vote', $postData)) {
			foreach ($options as $option) {
				if ($option->getID() == $postData['vote']) {
					$config->vote($option);
					return self::_redirect('/results');
				}
			}
		}
		return self::_respondBadRequest();
	}

	private static function _redirect(string $uri): int {
		header('HTTP/1.1 302 Found');
		header("Location: $uri");
		return 0;
	}

	// Supports placeholders:
	// {{ name }} will escape HTML in body context
	// {{ @name }} escapes in HTML attribute context
	// {{ !name }} does NOT escape anything (use with care!)
	private static function _renderTemplate(string $template, array $variables): string {
		return preg_replace_callback('/\\{\\{\\s*((?:!|@)?[\\-a-z]+)\\s*\\}\\}/', function (array $matches) use ($variables): string {
			list(, $key) = $matches;
			if (array_key_exists(ltrim($key, '!@'), $variables)) {
				switch ($key[0]) {
					case '!':
						return (string)$variables[substr($key, 1)];
					case '@':
						return htmlspecialchars((string)$variables[substr($key, 1)], ENT_HTML5 | ENT_QUOTES);
					default:
						return htmlspecialchars((string)$variables[$key], ENT_HTML5 | ENT_NOQUOTES);
				}
			} else {
				return '';
			}
		}, str_replace([ "\t", "\n" ], '', file_get_contents(self::TEMPLATE_PATH . "$template.html")));
	}

	private static function _respond(int $code, string $status, string $contentType = 'text/plain', string $body = NULL): int {
		header("HTTP/1.1 $code $status");
		header("Content-Type: $contentType; charset=\"UTF-8\"");
		echo $body ?? $status;
		return 0;
	}

	private static function _respondBadRequest(): int {
		return self::_respond(400, 'Bad Request');
	}

	private static function _respondMethodNotAllowed(): int {
		return self::_respond(405, 'Method Not Allowed');
	}

	private static function _respondNotFound(): int {
		return self::_respond(404, 'Not Found');
	}

	private static function _respondOK(string $title, string $content): int {
		return self::_respond(200, 'OK', 'text/html', self::_renderTemplate('base', [
			'title' => $title,
			'content' => $content,
		]));
	}

	public static function routeRequest(string $requestMethod, string $requestPath, array $postData): int {
		try {
			switch ($requestPath) {
				case '/':
					return self::_redirect('/vote');
				case '/vote':
					switch ($requestMethod) {
						case 'GET':
							return self::_handleGetVote();
						case 'POST':
							return self::_handlePostVote($postData);
						default:
							return self::_respondMethodNotAllowed();
					}
				case '/results':
					return self::_handleGetResults();
				default:
					return self::_respondNotFound();
			}
		} catch (Throwable $throwable) {
			return self::_respond(500, 'Internal Server Error', 'text/plain', $throwable->getMessage());
		}
	}
}

<?php

namespace rdx\imap;

class IMAPMessagePart implements IMAPMessagePartInterface {

	protected $section = [];
	protected $subtype = '';

	protected $parts = [];
	protected $parameters = [];

	protected $message; // typeof IMAPMessage
	protected $structure; // typeof stdClass
	protected $skippedParts = []; // Array<stdClass>

	public function __construct( IMAPMessage $message, $structure, array $section ) {
		$this->message = $message;
		$this->structure = $structure;
		$this->section = $section;
		$this->subtype = strtoupper($structure->subtype);

		if ( !empty($structure->parts) ) {
			$parts = $structure->parts;

			while ( count($parts) == 1 && empty($parts[0]->bytes) && !empty($parts[0]->parts) ) {
				$this->skippedParts[] = $parts[0];
				$parts = $parts[0]->parts;
			}

			foreach ( $parts as $n => $part ) {
				$this->parts[] = new IMAPMessagePart(
					$this->message(),
					$part,
					array_merge($this->section(), [$n+1])
				);
			}
		}
	}

	public function parts() {
		return $this->parts;
	}

	public function allParts( $withContainers = false ) {
		$parts = [];
		$iterate = function($message) use (&$iterate, &$parts, $withContainers) {
			foreach ( $message->parts() as $part ) {
				if ( $part->parts() ) {
					if ( $withContainers ) {
						$parts[] = $part;
					}

					$iterate($part);
				}
				else {
					$parts[] = $part;
				}
			}
		};

		$iterate($this);
		return $parts;
	}

	public function part( $index ) {
		$parts = $this->parts();
		return @$parts[$index];
	}

	public function structure() {
		return $this->structure;
	}

	public function parameters() {
		if ( empty($this->parameters) ) {
			$structure = $this->structure();

			$this->parameters['bytes'] = @$structure->bytes;

			foreach ((array) @$structure->parameters as $param) {
				$this->parameters[ strtolower($param->attribute) ] = $param->value;
			}
			foreach ((array) @$structure->dparameters as $param) {
				$this->parameters[ strtolower($param->attribute) ] = $param->value;
			}
		}

		return $this->parameters;
	}

	public function parameter( $name ) {
		$parameters = $this->parameters();
		return @$parameters[ strtolower($name) ];
	}

	public function content() {
		$body = imap_fetchbody(
			$this->message()->mailbox()->imap(),
			$this->message()->msgNumber(),
			implode('.', $this->section()),
			FT_PEEK
		);
		return $this->decode($body);
	}

	public function decode( $content ) {
		return quoted_printable_decode($content);
	}

	public function message() {
		return $this->message;
	}

	public function section() {
		return $this->section;
	}

	public function subtype() {
		return $this->subtype;
	}

}

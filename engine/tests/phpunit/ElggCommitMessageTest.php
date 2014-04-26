<?php
/**
 * Tests the commit message validator
 */

require dirname(dirname(dirname(dirname(__FILE__)))) . '/.scripts/ElggCommitMessage.php';

class ElggCommitMessageTest extends PHPUnit_Framework_TestCase {
	public function testBadFormats() {
		$msgs = array(
			'A bad commit message',
			'chore: Summary',
			'chore(): Summary',
			'chore(test):Summary',
			'chore(test):',
			'chore(test): ',
			"chore(test):\n",
		);
		$msg = new ElggCommitMessage();

		foreach ($msgs as $text) {
			$msg->setMsg($text);
			$this->assertFalse($msg->isValidFormat(), $text);
		}
	}

	public function testShouldIgnore() {
		$ignored = array(
			'Merge pull request',
			'Merge abc123 into def456',
			"Merge pull request abc123\nBut has other stuff, too",
			'Merge release 1.8.18 into master.',
			'Revert "fix(amd): removed elgg_require_js for backwards compatibility"

			This reverts commit 76584089bee2b3246c736edb6b250e149acf906f.

			Conflicts:
				engine/lib/views.php'
		);

		foreach ($ignored as $msg) {
			$msg = new ElggCommitMessage($msg);
			$this->assertTrue($msg->shouldIgnore(), $msg);
		}
	}

	public function testPartsNoBody() {
		$text = "chore(test): Summary";
		
		$msg = new ElggCommitMessage($text);

		$this->assertTrue($msg->isValidFormat());
		$this->assertSame('chore', $msg->getPart('type'));
		$this->assertSame('test', $msg->getPart('component'));
		$this->assertSame('Summary', $msg->getPart('summary'));
		$this->assertSame('', $msg->getPart('body'));
	}

	public function testPartsWithBody() {
		$text = "chore(test): Summary\nOptional body";
		$msg = new ElggCommitMessage($text);

		$this->assertTrue($msg->isValidFormat());
		$this->assertSame('chore', $msg->getPart('type'));
		$this->assertSame('test', $msg->getPart('component'));
		$this->assertSame('Summary', $msg->getPart('summary'));
		$this->assertSame('Optional body', $msg->getPart('body'));
	}

	public function testPartsExtendedBody() {
		$title = "chore(test): Summary";

		$body = <<<___MSG
Optional body

Fixes #123, #456
Refs #789
___MSG;
		$text = "$title\n$body";

		$msg = new ElggCommitMessage($text);

		$this->assertTrue($msg->isValidFormat());
		$this->assertSame('chore', $msg->getPart('type'));
		$this->assertSame('test', $msg->getPart('component'));
		$this->assertSame('Summary', $msg->getPart('summary'));
		$this->assertSame($body, $msg->getPart('body'));
	}

	public function testLineLength() {
		$text = "chore(test): But with long line";
		$msg = new ElggCommitMessage();
		$msg->setMaxLineLength(15);
		$msg->setMsg($text);
		$this->assertFalse($msg->isValidLineLength());
	}

	public function testFindLengthyLines() {
		$text = "This text is 33 characters long.";
		$this->assertSame(array(1), ElggCommitMessage::findLengthyLines($text, 30));

		$text2 = 'This line is only 22.';
		$this->assertSame(array(), ElggCommitMessage::findLengthyLines($text2, 30));

		$text3 = <<<___TEXT
This has multiple lines.
Some of which are not really very long at all.
Some are.
___TEXT;

		$this->assertSame(array(2), ElggCommitMessage::findLengthyLines($text3, 30));
	}

	public function testLengthyLines() {
		$text =<<<___MSG
chore(test): But with long line

The long line is down here. This line is much longer than the other.
And this one is short.
But here we go again with another long line.
___MSG;
		$msg = new ElggCommitMessage();
		$msg->setMaxLineLength(40);
		$msg->setMsg($text);
		$this->assertSame(array(3, 5), $msg->getLengthyLines());
	}

	public function testType() {
		$types = array(
			'feature',
			'fix',
			'docs',
			'chore',
			'perf',
			'security',
			'deprecate',
		);

		// make sure we have all the types
		$this->assertSame($types, ElggCommitMessage::getValidTypes(),
				"Mismatched valid types between test and class");

		foreach ($types as $type) {
			$msg = new ElggCommitMessage("{$type}(component): Summary");
			$this->assertTrue($msg->isValidType(), "Invalid type `$type`.");
		}
	}

	public function testRemoveComments() {
		$text = <<<___TEXT
These are lines of text
# this is a comment
# and another one.
And more text
___TEXT;

		$expected = "These are lines of text\nAnd more text";
		$this->assertSame($expected, ElggCommitMessage::removeComments($text));
	}
}

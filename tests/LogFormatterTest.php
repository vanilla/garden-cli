<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests;


use Garden\Cli\Cli;
use Garden\Cli\LogFormatter;

/**
 * Includes tests for the {@link \Garden\Cli\LogFormatter} class.
 */
class LogFormatterTest extends AbstractCliTest {

    /**
     * Create a new {@link LogFormatter} object with settings appropriate for most tests.
     *
     * @param int $maxLevel The max output level.
     * @return LogFormatter Returns the new {@link LogFormatter} object.
     */
    protected function createTestLogger($maxLevel = 2) {
        $log = new LogFormatter();
        $log->setDateFormat('[d]')
            ->setFormatOutput(false)
            ->setEol("\n")
            ->setMaxLevel($maxLevel)
            ->setShowDurations(false);
        return $log;
    }

    /**
     * Test a basic logger message.
     */
    public function testMessage() {
        $log = $this->createTestLogger();

        $log->message('Hello world!');
        $this->expectOutputString("[d] Hello world!\n");
    }

    /**
     * Make sure empty date formats don't add a space.
     */
    public function testEmptyDateFormat() {
        $log = $this->createTestLogger();
        $log->setDateFormat('');

        $log->message('No date.');
        $this->expectOutputString('No date.'.PHP_EOL);
    }

    /**
     * Test a two-level deep log.
     */
    public function testTwoLevels() {
        $log = $this->createTestLogger();

        $log->begin('Begin.')
            ->message('One.')
            ->message('Two.')
            ->end('End.');

        $this->expectOutputString(<<<EOT
[d] Begin.
[d] - One.
[d] - Two.
[d] End.

EOT
        );
    }

    /**
     * Test a three-level deep log with hidden values.
     */
    public function testThreeLevelsHidden() {
        $log = $this->createTestLogger();

        $log->begin('One')
            ->begin('Two')
            ->message('Three')
            ->end('Done')
            ->end('Done');

        $this->expectOutputString(<<<EOT
[d] One
[d] - Two Done
[d] Done

EOT
        );
    }

    /**
     * Test a message un-hidden by errors.
     */
    public function testThreeLevelsError() {
        $log = $this->createTestLogger();

        $log->begin('One')
            ->begin('Two')
            ->message('Three')
            ->error('Three Error')
            ->end('Done')
            ->end('Done');

        $this->expectOutputString(<<<EOT
[d] One
[d] - Two
[d]   - Three Error
[d] - Done
[d] Done

EOT
        );
    }

    /**
     * Test changing the max level to one and making sure it hides level two.
     */
    public function testMaxLevelOne() {
        $log = $this->createTestLogger(1);

        $log->begin('One')
            ->message('Two')
            ->end('Done');

        $this->expectOutputString(<<<EOT
[d] One Done

EOT
        );
    }

    /**
     * A subtask above the max level should be hidden.
     */
    public function testHiddenSubtask() {
        $log = $this->createTestLogger(1);

        $log->begin('One')
            ->begin('Two')
            ->end('Done Two')
            ->end('Done One');

        $this->expectOutputString(<<<EOT
[d] One Done One

EOT
        );
    }

    /**
     * A hidden heading should show if there is an error within it.
     */
    public function testHeadingShownWithHeader() {
        $log = $this->createTestLogger(1);

        $log->begin('One')
            ->begin('Two')
            ->message('Three')
            ->error('Three Error')
            ->end('Done Two')
            ->end('Done One');

        $this->expectOutputString(<<<EOT
[d] One
[d] - Two
[d]   - Three Error
[d] Done One

EOT
        );
    }

    /**
     * A hidden task that ends in error should be output.
     */
    public function testHiddenEndError() {
        $log = $this->createTestLogger(1);

        $log->begin('One')
            ->begin('Two')
            ->message('Hidden Three')
            ->endError('Error Two')
            ->end('Done One');

        $this->expectOutputString(<<<EOT
[d] One
[d] - Two Error Two
[d] Done One

EOT
        );
    }

    /**
     * Test the lower bounds of the various duration types.
     */
    public function testFormatDurationMinimums() {
        $log = new LogFormatter();
        $this->assertSame('1μs', $log->formatDuration(1e-6));
        $this->assertSame('1ms', $log->formatDuration(1e-3));
        $this->assertSame('1s', $log->formatDuration(1));
        $this->assertSame('1m', $log->formatDuration(60));
        $this->assertSame('1h', $log->formatDuration(strtotime('1 hour', 0)));
        $this->assertSame('1d', $log->formatDuration(strtotime('1 day', 0)));
    }

    /**
     * The {@link LogFormatter} should not take a negative max level.
     */
    public function testBadMaxLevel() {
        $log = new LogFormatter();
        $this->expectException(\InvalidArgumentException::class);
        $log->setMaxLevel(-1);
    }

    /**
     * Test calling {@LogFormatter::end()} too many times.
     */
    public function testTooManyEnds() {
        $log = $this->createTestLogger();

        $log->begin('Begin')
            ->end('done');

        @$log->end('done 2');

        $this->expectOutputString(<<<EOT
[d] Begin done
[d] done 2

EOT
        );

        $this->expectNotice();
        $log->end('done 2');
    }

    /**
     * Test some basic color formatting.
     */
    public function testColors() {
        $log = $this->createTestLogger();
        $log->setFormatOutput(true)
            ->setDateFormat('');

        $log->success('y')
            ->error('n');

        $this->expectOutputString(Cli::greenText('y')."\n".Cli::redText('n')."\n");
    }
}

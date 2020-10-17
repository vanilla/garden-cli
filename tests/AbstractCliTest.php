<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests;


use PHPUnit\Framework\TestCase;

abstract class AbstractCliTest extends TestCase {
    /**
     * Assert that a deep array is a subset of another deep array.
     *
     * @param array $subset The subset to test.
     * @param array $array The array to test against.
     * @param string $message A message to display on the test.
     */
    public static function assertArraySubsetRecursive(array $subset, array $array, $message = ''): void {
        self::filterArraySubset($array, $subset);
        self::assertSame($subset, $array, $message);
    }

    /**
     * Filter a parent array so that it doesn't include any keys that the child doesn't have.
     *
     * This also sorts the arrays by key so they can be compared.
     *
     * @param array $parent The subset to filter.
     * @param array $subset The parent array.
     */
    private static function filterArraySubset(array &$parent, array &$subset): void {
        $parent = array_intersect_key($parent, $subset);

        ksort($parent);
        ksort($subset);

        foreach ($parent as $key => &$value) {
            if (is_array($value) && isset($subset[$key]) && is_array($subset[$key])) {
                // Recurse into the array.
                self::filterArraySubset($value, $subset[$key]);
            }
        }
    }
}

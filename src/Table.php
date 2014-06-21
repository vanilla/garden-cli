<?php
namespace Garden\Cli;

/**
 * Used to write a formatted table to the console.
 */
class Table {
    /// Properties ///

    /**
     * @var int An array of column widths.
     */
    protected $columnWidths;

    /**
     * @var array An array of the row data.
     */
    protected $rows;

    /**
     * @var array A pointer to the current row.
     */
    protected $currentRow;

    /**
     * @var int The maximum width of the table.
     */
    public $maxWidth = 80;

    /**
     * @var int The left padding on each cell.
     */
    public $padding = 3;

    /**
     * @var int The left indent on the table.
     */
    public $indent = 2;


    /// Methods ///

    public function __construct() {
        $this->reset();
    }

    /**
     * Add a cell to the table.
     * @param string $text The text of the cell.
     * @param array $wrap A two element array used to wrap the text in the cell.
     * @return $this Returns this object for fluent calls.
     */
    protected function addCell($text, $wrap = ['', '']) {
        if ($this->currentRow === null) {
            $this->row();
        }

        $i = count($this->currentRow);
        $this->columnWidths[$i] = max(strlen($text), Cli::val($i, $this->columnWidths, 0)); // max column width

        $this->currentRow[$i] = [$text, $wrap];
        return $this;
    }

    /**
     * Adds a cell.
     *
     * @param string $text The text of the cell.
     * @return $this Returns this object for fluent calls.
     */
    public function cell($text) {
        return $this->addCell($text);
    }

    /**
     * Adds a bold cell.
     *
     * @param string $text The text of the cell.
     * @return $this Returns this object for fluent calls.
     */
    public function bold($text) {
        return $this->addCell($text, ["\033[1m", "\033[0m"]);
    }

    /**
     * Adds a red cell.
     *
     * @param string $text The text of the cell.
     * @return $this Returns this object for fluent calls.
     */
    public function red($text) {
        return $this->addCell($text, ["\033[1;31m", "\033[0m"]);
    }

    /**
     * Adds a green cell.
     *
     * @param string $text The text of the cell.
     * @return $this Returns this object for fluent calls.
     */
    public function green($text) {
        return $this->addCell($text, ["\033[1;32m", "\033[0m"]);
    }

    /**
     * Adds a blue cell.
     *
     * @param string $text The text of the cell.
     * @return $this Returns this object for fluent calls.
     */
    public function blue($text) {
        return $this->addCell($text, ["\033[1;34m", "\033[0m"]);
    }

    /**
     * Adds a purple cell.
     * 
     * @param string $text The text of the cell.
     * @return $this Returns this object for fluent calls.
     */
    public function purple($text) {
        return $this->addCell($text, ["\033[0;35m", "\033[0m"]);
    }

    /**
     * Reset the table so another one can be written with the same object.
     */
    public function reset() {
        $this->columnWidths = [];
        $this->rows = [];
        $this->currentRow = null;
    }

    /**
     * Start a new row.
     * @return $this Returns this object for fluent calls.
     */
    public function row() {
        $this->rows[] = [];
        $this->currentRow =& $this->rows[count($this->rows) - 1];
        return $this;
    }

    /**
     * Writes the final table.
     */
    public function write() {
        // Determine the width of the last column.
        $columnWidths = array_sum($this->columnWidths);
        $totalWidth = $this->indent + $columnWidths + $this->padding * (count($this->columnWidths) - 1);

        $lastWidth = end($this->columnWidths) + $this->maxWidth - $totalWidth;
        $lastWidth = max($lastWidth, 10); // min width of 10
        $this->columnWidths[count($this->columnWidths) - 1] = $lastWidth;

        // Loop through each row and write it.
        foreach ($this->rows as $row) {
            $rowLines = [];
            $lineCount = 0;

            // Split the cells into lines.
            foreach ($row as $i => $cell) {
                list($text, $wrap) = $cell;
                $width = $this->columnWidths[$i];

                $lines = Cli::breakLines($text, $width, $i < count($this->columnWidths) - 1);
                $rowLines[] = $lines;
                $lineCount = max($lineCount, count($lines));
            }

            // Write all of the lines.
            for ($i = 0; $i < $lineCount; $i++) {
                foreach ($rowLines as $j => $lines) {
                    $padding = $j === 0 ? $this->indent : $this->padding;

                    if (isset($lines[$i])) {
                        $wrap = $row[$i][1];

                        echo str_repeat(' ', $padding).$wrap[0].$lines[$i].$wrap[1];
                    } elseif ($j < count($this->columnWidths) - 1) {
                        // This is an empty line. Write the spaces.
                        echo str_repeat(' ', $padding + $this->columnWidths[$j]);
                    }
                }
                echo "\n";
            }
        }
    }
}
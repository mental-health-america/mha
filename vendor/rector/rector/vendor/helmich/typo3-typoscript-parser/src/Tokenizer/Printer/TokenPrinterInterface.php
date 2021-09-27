<?php

declare (strict_types=1);
namespace RectorPrefix20210927\Helmich\TypoScriptParser\Tokenizer\Printer;

use RectorPrefix20210927\Helmich\TypoScriptParser\Tokenizer\TokenInterface;
/**
 * Interface definition for a class that prints token streams
 *
 * @package    Helmich\TypoScriptParser
 * @subpackage Tokenizer\Printer
 */
interface TokenPrinterInterface
{
    /**
     * @param TokenInterface[] $tokens
     * @return string
     */
    public function printTokenStream($tokens) : string;
}

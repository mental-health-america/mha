<?php

declare (strict_types=1);
namespace RectorPrefix20210927\Doctrine\Inflector\Rules\NorwegianBokmal;

use RectorPrefix20210927\Doctrine\Inflector\GenericLanguageInflectorFactory;
use RectorPrefix20210927\Doctrine\Inflector\Rules\Ruleset;
final class InflectorFactory extends \RectorPrefix20210927\Doctrine\Inflector\GenericLanguageInflectorFactory
{
    protected function getSingularRuleset() : \RectorPrefix20210927\Doctrine\Inflector\Rules\Ruleset
    {
        return \RectorPrefix20210927\Doctrine\Inflector\Rules\NorwegianBokmal\Rules::getSingularRuleset();
    }
    protected function getPluralRuleset() : \RectorPrefix20210927\Doctrine\Inflector\Rules\Ruleset
    {
        return \RectorPrefix20210927\Doctrine\Inflector\Rules\NorwegianBokmal\Rules::getPluralRuleset();
    }
}
<?php

namespace RectorPrefix20210927;

if (\class_exists('t3lib_extTables_PostProcessingHook')) {
    return;
}
class t3lib_extTables_PostProcessingHook
{
}
\class_alias('t3lib_extTables_PostProcessingHook', 't3lib_extTables_PostProcessingHook', \false);

<?php

namespace RectorPrefix20210927;

if (\class_exists('Tx_Extbase_Validation_Error')) {
    return;
}
class Tx_Extbase_Validation_Error
{
}
\class_alias('Tx_Extbase_Validation_Error', 'Tx_Extbase_Validation_Error', \false);

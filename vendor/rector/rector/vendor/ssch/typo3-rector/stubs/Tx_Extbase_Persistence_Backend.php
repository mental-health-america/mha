<?php

namespace RectorPrefix20210927;

if (\class_exists('Tx_Extbase_Persistence_Backend')) {
    return;
}
class Tx_Extbase_Persistence_Backend
{
}
\class_alias('Tx_Extbase_Persistence_Backend', 'Tx_Extbase_Persistence_Backend', \false);

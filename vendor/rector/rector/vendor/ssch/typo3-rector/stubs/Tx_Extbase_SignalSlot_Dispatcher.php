<?php

namespace RectorPrefix20210927;

if (\class_exists('Tx_Extbase_SignalSlot_Dispatcher')) {
    return;
}
class Tx_Extbase_SignalSlot_Dispatcher
{
}
\class_alias('Tx_Extbase_SignalSlot_Dispatcher', 'Tx_Extbase_SignalSlot_Dispatcher', \false);

<?php

namespace RectorPrefix20210927;

if (\class_exists('tx_lowlevel_lost_files')) {
    return;
}
class tx_lowlevel_lost_files
{
}
\class_alias('tx_lowlevel_lost_files', 'tx_lowlevel_lost_files', \false);

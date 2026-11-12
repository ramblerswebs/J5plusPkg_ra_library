<?php

//namespace RamblerWebs\RamblersLibrary\jsonwalks;

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks;

enum TypeOfWalk: string {

    case Unknown = '?';
    case GroupWalk = 'Walk';
    case GroupEvent = 'Event';
    case WellbeingWalk = 'Well being walk';
}

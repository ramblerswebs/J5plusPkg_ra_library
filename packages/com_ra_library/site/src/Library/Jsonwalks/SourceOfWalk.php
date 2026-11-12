<?php

//namespace RamblerWebs\RamblersLibrary\jsonwalks;

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks;

enum SourceOfWalk: string {

    case Unknown = '?';
    case GWEM = 'gwem';
    case WManager = 'wm';
    case WEditor = 'we';
}

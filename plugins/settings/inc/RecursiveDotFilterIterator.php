<?php
namespace Plugins\Settings\Inc;

use RecursiveFilterIterator;

class RecursiveDotFilterIterator extends RecursiveFilterIterator
{
    public function accept()
    {
        return '.' !== substr($this->current()->getFilename(), 0, 1);
    }
}

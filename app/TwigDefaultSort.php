<?php

declare(strict_types=1);

namespace App;

use App\Contracts\SortInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigDefaultSort extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('get_default_sort', [$this, 'getDefaultSort']),
        ];
    }

    public function getDefaultSort(SortInterface $sort): string
    {
        return $sort::default()->value;
    }
}

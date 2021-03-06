<?php

namespace Zikula\Core\Theme\Asset;

use Zikula\Core\Theme\AssetBag;

class JsResolver implements ResolverInterface
{
    private $bag;

    public function __construct(AssetBag $bag)
    {
        $this->bag = $bag;
    }

    public function compile()
    {
        $headers = '';
        foreach ($this->bag->all() as $asset) {
            $headers .= '<script type="text/javascript" src="'.$asset.'"></script>'."\n";
        }

        return $headers;
    }

    public function getBag()
    {
        return $this->bag;
    }
}

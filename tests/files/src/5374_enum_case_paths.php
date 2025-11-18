<?php

namespace NS5374;

enum XSDPaths: string
{
    case IRELAND = __DIR__ . '/XSD/ie/dac7_irish_revenue_version.xsd';
    case CANADA = __DIR__ . '/XSD/ca/canada_T619_PartXX_v1.0.xsd';
}

function accepts(XSDPaths $path): string
{
    return $path->value;
}

<?php
## .SCOT, .CAT, .BARCELONA, .MADRID, .SPORT DOMAIN REQUIREMENTS ##
$additionaldomainfields[$tld] = array();
$additionaldomainfields[$tld][] = array(
    "Name" => "Intended Use",
    "Type" => "text",
    "Required" => true,
    "Ispapi-Name" => "X-CORE-INTENDED-USE"
);

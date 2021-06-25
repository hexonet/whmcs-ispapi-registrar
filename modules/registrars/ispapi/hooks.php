<?php

use WHMCS\Module\Registrar\Ispapi\Ispapi;
use WHMCS\Module\Registrar\Ispapi\Helper;
use WHMCS\Module\Registrar\Ispapi\Domain as HXDomain;
use WHMCS\Module\Registrar\Ispapi\AdditionalFields as AF;
use WHMCS\Module\Registrar\Ispapi\DomainTrade as HXTrade;
use WHMCS\Module\Registrar\Ispapi\Lang as L;
use Illuminate\Database\Capsule\Manager as DB;

$path = implode(DIRECTORY_SEPARATOR, [__DIR__, "hooks_migration.php"]);
if (file_exists($path)) {
    include $path;
}

add_hook("ShoppingCartValidateCheckout", 1, function ($vars) {
    // load registrar functions
    if (!function_exists("getregistrarconfigoptions")) {
        include implode(DIRECTORY_SEPARATOR, [ROOTDIR, "includes", "registrarfunctions.php"]);
    }

    // error container
    $errors = [];

    // load registrar module settings and check if transfer precheck are activated
    $regcfg = getregistrarconfigoptions("ispapi");
    $cartprecheck = ($regcfg["TRANSFERCARTPRECHECK"] === "on");
    if (!$cartprecheck || empty($_SESSION["cart"]["domains"])) {
        return $errors;
    }

    // precheck all transfers
    foreach ($_SESSION["cart"]["domains"] as $d) {
        if ($d["type"] === "transfer") {
            $tld = preg_replace("/^[^.]+\./", "", $d["domain"]);
            // check if the registrar configured for that tld is `ispapi`
            $count = DB::table("tbldomainpricing")
                ->select("autoreg")
                ->where("extension", "." . $tld)
                ->where("autoreg", "ispapi")
                ->count();
            if (!$count) {
                continue;
            }
            $cmd = [
                "COMMAND" => "CheckDomainTransfer",
                "DOMAIN" => $d["domain"]
            ];
            if (!empty($d["eppcode"])) {
                $cmd["AUTH"] = $d["eppcode"];
            }
            $r = Ispapi::call($cmd);
            if ($r["CODE"] === "219") {
                $errors[] = $d["domain"] . ": " . $r["DESCRIPTION"];
            }
        }
    }
    return $errors;
});

add_hook("ClientAreaHeadOutput", 1, function ($vars) {
    $domain = Menu::context("domain");
    if (!$domain || $domain->registrar !== "ispapi") {
        return "";
    }

    $html = "";
    // ---------------------------------------
    // Inject Private NS List into private NS Page
    // ---------------------------------------
    if (isset($_REQUEST["action"]) && $_REQUEST["action"] === "domainregisterns") {
        // load registrar functions
        if (!function_exists("getregistrarconfigoptions")) {
            include implode(DIRECTORY_SEPARATOR, [ROOTDIR, "includes", "registrarfunctions.php"]);
        }
        // load registrar module settings and check if transfer precheck are activated
        $params = getregistrarconfigoptions("ispapi");

        $r = HXDomain::getStatus($params, $domain->domain, true);
        if ($r["success"] && !empty($r["data"]["HOST"])) {
            $col1 = L::trans("domainregisternsns");
            $col2 = L::trans("domainregisternsip");
            $html .= "<table class=\"table table-bordered table-striped\"><thead><tr><th>$col1</th><th>$col2</th></tr></thead><tbody>";
            foreach ($r["data"]["HOST"] as $host) {
                list($hname, $ip) = explode(" ", $host);
                $html .= "<tr><td>$hname</li></td><td>$ip</td></tr> ";
            }
            $html .= "</tbody></table>";
            return <<< HTML
                <script type="text/javascript">
                    const ispapi_pns_html = '$html';
                    $(document).ready(function(){
                        const form = $('form[action="/clientarea.php?action=domainregisterns"]').first();
                        form.before(ispapi_pns_html);
                    })
                </script>
    HTML;
            return $html;
        }
    }
    // ---------------------------------------
    // Inject Fields into Contact Details Page
    // ---------------------------------------
    if (isset($_REQUEST["action"]) && $_REQUEST["action"] === "domaincontacts") {
        // load registrar functions
        if (!function_exists("getregistrarconfigoptions")) {
            include implode(DIRECTORY_SEPARATOR, [ROOTDIR, "includes", "registrarfunctions.php"]);
        }
        // load registrar module settings and check if transfer precheck are activated
        $params = getregistrarconfigoptions("ispapi");

        $tradeType = HXTrade::affectsRegistrantModification($params, $domain->domain, ["TRADE", "ICANN-TRADE"]);
        // Standard Trade and IRTP with no DA Authorization -> non-realtime
        $showTradeInfo = (
            (bool)$tradeType
            && (
                ($tradeType !== "ICANN-TRADE")
                || preg_match("/Designated Agent/", $params["IRTP"])
            )
        );
        $addflds = new AF($params["TestMode"] === "on");
        $addflds->setDomainType($tradeType ? "trade" : "update")
                ->setDomain($domain->domain)
                ->getFieldValuesFromDatabase($domain->id);
        $fields = $addflds->getFieldsForOutput();
        $tradeInfo = L::trans('hxdomaincontactstradeinfo');
        if (!empty($fields)) {
            $html .= <<<HTML
            <script type="text/javascript">
                const ispapi_show_tradeInfo = $showTradeInfo;
                const ispapi_tradeInfo = '<br/>$tradeInfo';
                const ispapi_fields_html = '$fields';
                $(document).ready(function(){
                    const form = $('form[action="/clientarea.php?action=domaincontacts"]');
                    if (ispapi_show_tradeInfo) {
                        form.prev().append(ispapi_tradeInfo);
                    }
                    form.children().last().before(ispapi_fields_html);
                })
            </script>
HTML;
        }
    }
    return $html;
});

add_hook("AfterRegistrarRegistrationFailed", 1, function ($vars) {
    // ONLY FOR .SWISS: saves the .swiss application ID in the admin note
    $params = $vars["params"];
    $domain = $params["sld"] . "." . $params["tld"];
    if (preg_match("/[.]swiss$/i", $domain)) {
        preg_match("/<#(.+?)#>/i", $vars["error"], $matches);
        if (isset($matches[1])) {
            $application_id = $matches[1];
            $data = [
                ":id" => $params["domainid"],
                ":notes" => "### DO NOT DELETE ANYTHING BELOW THIS LINE \nAPPLICATION:" . $application_id . "\n"
            ];
            Helper::SQLCall("UPDATE tbldomains SET additionalnotes=:notes WHERE id=:id", $data, "execute");
        }
    }
});

add_hook("DailyCronJob", 1, function ($vars) {
    //Save information about module versions in the environment
    Ispapi::sendStatisticsData();

    // ONLY FOR .SWISS: runs over all pending applications to check if the registration was successful or not.
    $data = [
        ":registrar" => "ispapi",
        ":stat" => "Pending"
    ];
    $r = Helper::SQLCall(
        "SELECT id, additionalnotes FROM tbldomains WHERE additionalnotes!=\"\" AND registrar=:registrar AND status=:stat",
        $data,
        "fetchall"
    );
    if ($r["success"]) {
        foreach ($r["result"] as $row) {
            preg_match("/APPLICATION:(.+?)(?:$|\n)/i", $row["additionalnotes"], $matches);
            if (isset($matches[1])) {
                $data = [
                    ":id" => $row["id"]
                ];
                $response = Ispapi::call([
                    "COMMAND" => "StatusDomainApplication",
                    "APPLICATION" => $matches[1]
                ]);
                if ($response["PROPERTY"]["STATUS"][0] === "SUCCESSFUL") {
                    //echo $row["domain"]." > Status:".$response["PROPERTY"]["STATUS"][0];
                    $data[":stat"] = "Active";
                    Helper::SQLCall("UPDATE tbldomains SET status=:stat WHERE id=:id", $data, "execute");
                }
                if ($response["PROPERTY"]["STATUS"][0] === "FAILED") {
                    //echo $row["domain"]." > Status:".$response["PROPERTY"]["STATUS"][0];
                    $data[":stat"] = "Cancelled";
                    Helper::SQLCall("UPDATE tbldomains SET status=:stat WHERE id=:id", $data, "execute");
                }
            }
        }
    }
});

$ispapi_domainMenuUpdate = function ($vars) {
    // TLDs not supporting Transfer Lock: remove "Registrar Lock" menu entry.
    $domain = Menu::context("domain");

    if ($domain->registrar === "ispapi") {
        // load registrar functions
        if (!function_exists("getregistrarconfigoptions")) {
            require_once implode(DIRECTORY_SEPARATOR, [ROOTDIR, "includes", "registrarfunctions.php"]);
        }

        $menu = $vars["primarySidebar"]->getChild("Domain Details Management");

        $r = HXDomain::getRegistrarLock(getregistrarconfigoptions("ispapi"), $domain->domain);
        if (isset($r["error"])) {
            $vars["managementoptions"]["locking"] = false;
            $vars["lockstatus"] = false;
            if (!is_null($menu)) {
                $menu->removeChild("Registrar Lock Status");
            }
        }
        return $vars;
    }
};

add_hook("ClientAreaPageDomainDNSManagement", 1, $ispapi_domainMenuUpdate);
add_hook("ClientAreaPageDomainEPPCode", 1, $ispapi_domainMenuUpdate);
add_hook("ClientAreaPageDomainContacts", 1, $ispapi_domainMenuUpdate);
add_hook("ClientAreaPageDomainRegisterNameservers", 1, $ispapi_domainMenuUpdate);
add_hook("ClientAreaPageDomainDetails", 1, $ispapi_domainMenuUpdate);

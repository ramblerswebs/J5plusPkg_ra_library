<?php
namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std;

/**
 * Description of Leadertable
 *
 * @author Chris Vaughan
 */
// no direct access
defined("_JEXEC") or die("Restricted access");
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Displaybase;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Walk;
use Ramblers\Component\Ra_library\Site\Library\Html\Html;

class Leaderstable extends Displaybase {

    private $tableClass = "";

    function DisplayWalks($walks) {
        $walks->sort(Walk::SORT_CONTACT, Walk::SORT_TELEPHONE1, NULL);
        $items = $walks->allWalks();
        $last = "";

        echo "<table class='$this->tableClass'>";
        echo Html::addTableHeader(array("Contact", "Telephone 1", "Telephone 2"));
        foreach ($items as $walk) {
            $contactName = $walk->getIntValue("contacts", "contactName");
            $telephone1 = $walk->getIntValue("contacts", "telephone1");
            $telephone2 = $walk->getIntValue("contacts", "telephone2");
            $value = $contactName . " - " . $telephone1;
            $value .= " ," . $telephone2;
            if ($value <> $last) {
                echo Html::addTableRow(array($contactName, $telephone1, $telephone2));
                $last = $value;
            }
        }
        echo "</table>" . PHP_EOL;
    }

    function setTableClass($class) {
        $this->tableClass = $class;
    }
}

<?php
/**
 * This example shows how to use the CoolMenu output driver
 *
 * @author Andy Crain <apcrain@fuse.net>
 */

// This example assumes that you have allready set up DB_NestedSet and allready
// inserted nodes.

// First you have to get CoolMenu
// It's available for free at http://www.dhtmlcentral.com/projects/coolmenus
// There are a lot of parameters required by CoolMenu, so the options array required
// by this driver is fairly large. There are many combinations of parameters that
// determine menu output, so it is strongly recommended you read the CoolMenu documents.

require_once('DB/NestedSet.php');
require_once('DB/NestedSet/Output.php');

// Choose a database abstraction layer. 'DB' and 'MDB' are supported.
$nese_driver = 'DB';

// Set the DSN - see http://pear.php.net/manual/en/core.db.tut_dsn.php for details
$nese_dsn = 'type://user:password@server/db';

// Specify the database columns which will be used to specify a node
// Use an associative array. On the left side write down the name of the column.
// On the right side write down how the property will be called in a node object
// Some params are needed
$nese_params = array
(
    "STRID"         =>      "id",      // "id" must exist
    "ROOTID"        =>      "rootid",  // "rootid" must exist
    "l"             =>      "l",       // "l" must exist
    "r"             =>      "r",       // "r" must exist
    "STREH"         =>      "norder",  // "order" must exist
    "LEVEL"         =>      "level",   // "level" must exist
    "STRNA"         =>      "name",
    "STLNK"         =>      "link"     // Custom - specify as many fields you want
);

// Now create an instance of DB_NestedSet
$NeSe = & DB_NestedSet::factory($nese_driver, $nese_dsn, $nese_params);
if(PEAR::isError($NeSe)) {
    echo $NeSe->getCode().": ".$NeSe->getMessage();
}


$NeSe->setAttr(array(
        'node_table' => 'foo',
        'lock_table' => 'bar',
        'sequence_table' => 'foobar'
    )
);


// Fetch the tree as array
$nodes = $NeSe->getAllNodes(true);

// Set the basic params
$params = array(
'menu_id' => 1,
'structure' => $nodes,
'options' => array(
),
'textField' => 'mytextfield', // Use the name column for the menu names
'linkField' => 'mylinkfield', // Use the link column for the links
'currentLevel' => 1       // Start the ouput with this level
);

// This array contains the options needed
// for printing out the menu.



/*
The options array holds everything CoolMenu needs to know to build a menu.

$options['levels'] defines structure and style for particular menu levels.
It needs at least one element, defining the root/top-level, and
any number of additional elements, for sublevel 1, sublevel 2...sublevel n. Any sublevel
property not defined inherits from the above, i.e. sublevel 1 inherits from
$options['levels'][0] if $options['levels'][1], or a part of it, is not defined.

$options['levels'][0] needs arrays for "mouseout_style", "mouseover_style", "border_style",
and "properties", the first three of which are used to build CSS definitions like so:
mouseout array('position' => 'absolute','padding' => '2px') becomes
".mouseout_style0{position:absolute; padding:2px;}" and so on.
The "properties" array defines a menu level's geometry. You can also add arrays
($options['levels'][0]['highlight_mouseout_style'] and
$options['levels'][0]['highlight_mouseout_style'])in the top level for highlighting if
you want the current page's menu tab highlighted.

$options['menu'] defines menu level structure and style.

Every element is a string. Note that some of these strings must include double quotes
(see http://www.dhtmlcentral.com/projects/coolmenus/).

Every parameter below (except for the style parameters, which are simply any valid CSS
key-value pairs) is defined in the CoolMenu reference at
http://www.dhtmlcentral.com/projects/coolmenus/, which you are stronly encouraged to read.
*/

$options = array(
    'levels' => array(//required
        0 => array(//required
            'mouseout_style' => array(//required; set whatever CSS elements you wish
                'position' => 'absolute',
                'padding' => '2px',
                'font-family' => 'tahoma,arial,helvetica',
                'font-size' => '11px',
                'font-weight' => 'bold',
                'background-color' => '#9DCDFE',
                'layer-background-color' => '#9DCDFE',
                'color' => 'white',
                'text-align'=>'center',
                'text-transform' => 'uppercase',
                'white-space' => 'nowrap',
                'border-top' => '1px solid #006699',
                'border-left' => '1px solid #006699',
                'border-right' => '1px solid #006699',
                'border-bottom' => '1px solid #006699'
            ),
            'mouseover_style' => array(//required; set whatever CSS elements you wish
                'position' => 'absolute',
                'padding' => '2px',
                'font-family' => 'tahoma,arial,helvetica',
                'font-size' => '11px',
                'font-weight' => 'bold',
                'background-color' => '#9DCDFE',
                'layer-background-color' => '#9DCDFE',
                'color' => '#006699',
                'cursor' => 'pointer',
                'cursor' => 'hand',
                'text-align'=>'center',
                'text-transform' => 'uppercase',
                'white-space' => 'nowrap',
                'border-top' => '1px solid #006699',
                'border-left' => '1px solid #006699',
                'border-right' => '1px solid #006699',
                'border-bottom' => '1px solid #006699'
            ),
            'border_style' => array(//optional; set whatever CSS elements you wish
                'position' => 'absolute',
                'visibility' => 'hidden',
                'width' => '0'
            ),
            'properties' => array(//required; each parameter here is required for level 0 at least
                'width' => '90',
                'height' => '20',
                'borderX' => '1',
                'borderY' => '1',
                'offsetX' => '0',
                'offsetY' => '0',
                'rows' => '0',
                'arrow' => '0',
                'arrowWidth' => '0',
                'arrowHeight' => '0',
                'align' => '"bottom"'
            ),
            /*
            The next two elements are optional, and only work when the linkField values
            represent local, current pages--i.e. these styles will apply when
            $node[$params['linkField']] == basename($_SERVER['PHP_SELF']). When that is true,
            these styles will override the toplevel mouseout and mouseover styles above. Useful
            if you want the current page highlighted in the menu.
            */
            'highlight_mouseout_style' => array(//optional; set whatever CSS elements you wish
                'position' => 'absolute',
                'padding' => '2px',
                'font-family' => 'tahoma,arial,helvetica',
                'font-size' => '11px',
                'font-weight' => 'bold',
                'background-color' => '#E5F1FF',
                'layer-background-color' => '#E5F1FF',
                'color' => '#006699',
                'text-align'=>'center',
                'text-transform' => 'uppercase',
                'white-space' => 'nowrap',
                'border-top' => '1px solid #006699',
                'border-left' => '1px solid #006699',
                'border-right' => '1px solid #006699',
            ),
            'highlight_mouseover_style' => array(//optional; set whatever CSS elements you wish
                'position' => 'absolute',
                'padding' => '2px',
                'font-family' => 'tahoma,arial,helvetica',
                'font-size' => '11px',
                'font-weight' => 'bold',
                'background-color' => '#E5F1FF',
                'layer-background-color' => '#E5F1FF',
                'color' => '#006699',
                'cursor' => 'pointer',
                'cursor' => 'hand',
                'text-align'=>'center',
                'text-transform' => 'uppercase',
                'white-space' => 'nowrap',
                'border-top' => '1px solid #006699',
                'border-left' => '1px solid #006699',
                'border-right' => '1px solid #006699',
            )//end highlight styles
        ),
        1 => array(//optional; define whatever should not be inherited from above
            'mouseout_style' => array(//optional; set whatever CSS elements you wish
                'position' => 'absolute',
                'padding' => '2px',
                'font-family' => 'tahoma,arial,helvetica',
                'font-size' => '10px',
                'font-weight' => 'bold',
                'background-color' => '#9DCDFE',
                'layer-background-color' => '#9DCDFE',
                'color' => 'white',
                'text-align'=>'center',
                'text-transform' => 'uppercase',
                'white-space' => 'nowrap'
            ),
            'mouseover_style' => array(//optional; set whatever CSS elements you wish
                'position' => 'absolute',
                'padding' => '2px',
                'font-family' => 'tahoma,arial,helvetica',
                'font-size' => '10px',
                'font-weight' => 'bold',
                'background-color' => '#9DCDFE',
                'layer-background-color' => '#9DCDFE',
                'color' => '#006699',
                'cursor' => 'pointer',
                'cursor' => 'hand',
                'text-align'=>'center',
                'text-transform' => 'uppercase',
                'white-space' => 'nowrap'
            ),
            'border_style' => array(//optional; set whatever CSS elements you wish
                'position' => 'absolute',
                'visibility' => 'hidden',
                'background-color' => '#006699',
                'layer-background-color' => '#006699'
            ),
            'properties' => array(//optional; set whatever elements you wish
                'width' => '100',
                'offsetX' => '-10',
                'offsetY' => '10',
                'align' => '"right"',
                'arrow' => '"menu_arrow.gif"',
                'arrowWidth' => '10',
                'arrowHeight' => '9',
            )
        )
    ),
    'menu' => array(//required
        'background_style' => array(//optional; set whatever CSS elements you wish
            'position' => 'absolute',
            'width' => '10',
            'height' => '10',
            'background-color' => '#99CC00',
            'layer-background-color' => '#99CC00',
            'visibility' => 'hidden',
            'border-bottom' => '1px solid #006699'
        ),
        'properties' => array(//required; all parameters are required
            'frames ' => '0',
            //Menu properties
            'pxBetween' => '5',
            'fromLeft' => '0',
            'fromTop' => '30',
            'rows' => '1',
            'menuPlacement' => '"center"',
            'resizeCheck' => '1',
            'wait' => '300',
            'fillImg' => '"cm_fill.gif"',
            'zIndex' => '0',
            'offlineRoot' => '"file:///' . $_SERVER['DOCUMENT_ROOT'] . '"',
            'onlineRoot' => '"/www/javascript/coolmenus/"',//path from web root to CM directory
            //Background bar properties
            'useBar' => '1',
            'barWidth' => '"100%"',
            'barHeight' => '51',
            'barX' => '0',
            'barY' => '0',
            'barBorderX' => '0',
            'barBorderY' => '0',
            'barBorderClass' => '""'
        )
    )
);


// Now create the menu object, set the options and do the output
$menu =& DB_NestedSet_Output::factory($params, 'CoolMenu');
$menu->setOptions('printTree', $options);
?>


<html>
<head>
<title>DB_NestedSet_CoolMenu usage example</title>
</head>
<body>

<script language="JavaScript1.2" src="/www/javascript/coolmenus/coolmenus4.js">
</script>

<?php
$menu->printTree();
?>

</body>
</html>
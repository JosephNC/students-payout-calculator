<?php

require_once 'StudentsPayoutCalculator.php';

if (PHP_SAPI != "cli") {
    throw new Error( "This is not CLI." );
    exit;
}

if ( $argc != 3 ) {
    throw new Error( "Input file must be workplaces and attendance." );
    exit;
}

if ( ! class_exists( 'StudentsPayoutCalculator' ) ) {
    throw new Error( "Soemthing went wrong." );
    exit;
}

$payout_calculator = new StudentsPayoutCalculator( $argv[1], $argv[2] );

$data = $payout_calculator->calculate();

// print_r( $data );

// Send to STDOUT
echo "id,payout";
foreach( $data as $id => $payout ) echo "\n$id,$payout";
?>
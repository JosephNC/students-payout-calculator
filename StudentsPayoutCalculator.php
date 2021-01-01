<?php

/**
 * The daily rates table
 * 
 * Age < 18     = 72.50
 * Age 18 - 24  = 81.00
 * Age 25       = 85.90
 * Age 26+      = 90.50
 * 
 */

/**
 * The students payout calculator class
 */
class StudentsPayoutCalculator
{
    private $workplaces = [];

    private $attendances = [];

    private $table = [
        'basic'     => [
            0   => 72.50,
            18  => 81.00,
            25  => 85.90,
            26  => 90.50,
        ],
        'meal'      => 5.50, // Per day
        'travel'    => 1.09, // Per km
        'fuel'      => 1.00, // Per day
    ];

    /**
     * Class constructor
     * 
     * @param string $workplaces_file
     * @param string $attendance_file
     */
    public function __construct( string $workplaces_file, string $attendance_file )
    {
        $this->workplaces = $this->fileToArray( $workplaces_file );
        $this->attendances = $this->fileToArray( $attendance_file );
    }

    /**
     * Converts csv file to PHP array.
     * It uses the first line as the array keys
     * for other lines in the file.
     * 
     * @param string $input_file   The input file
     * @return array 
     */
    private function fileToArray( string $input_file ) : array
    {
        $keys = [];
        $data = [];
        $is_first = true;

        $file = fopen($input_file, "r");
        
        while (($line = fgetcsv($file)) !== false) {
            if ( $is_first ) {
                $keys = $line;
                $is_first = false;
                continue;
            }
        
            $data[] = array_combine( $keys, $line );
        }

        fclose($file);

        return $data;
    }

    public function calculate()
    {
        $students = [];

        foreach( $this->attendances as $attendance ) {
            if (
                ! isset(
                    $attendance['id'],
                    $attendance['dob'],
                    $attendance['status'],
                    $attendance['location'],
                    $attendance['workplace_id']
                )
            ) continue;

            // Calculate the student's age
            $age = abs( (int) ( new DateTime($attendance['dob']) )->diff(new DateTIme())->y );
            $status = $attendance['status'];

            // Further constraints are considered based on the attendance status.
            if ( in_array( $status, [ 'AL', 'CSL' ] ) ) {
                $basic = $this->getBasicAllowance( $age );
                $meal = 0;
                $travel = 0;
            } else if ( in_array( $status, [ 'USL' ] ) ) {
                $basic = 0;
                $meal = 0;
                $travel = 0;
            } else {
                $basic = $this->getBasicAllowance( $age );
                $travel = $this->getTravelAllowance( $attendance['location'], $this->getWorkplacePoints( (int) $attendance['workplace_id'] ) );
                $meal = $this->getMealAllowance();
            }

            $student_id = (int) $attendance['id'];

            // $students[ $student_id ]['age'][] = $age;
            // $students[ $student_id ]['status'][] = $status;
            // $students[ $student_id ]['basic'][] = (float) $basic;
            // $students[ $student_id ]['meal'][] = (float) $meal;
            // $students[ $student_id ]['travel'][] = (float) $travel;

            $value = number_format( ($students[ $student_id ] ?? 0) + (float) $basic + (float) $meal + (float) $travel, 2, '.', '' );
            $students[ $student_id ] = $value;
        }

        // Order the student by ID
        ksort( $students );

        return $students;
    }

    private function getWorkplacePoints( int $workplace_id )
    {
        $location = '';

        foreach( $this->workplaces as $workplace ) {
            if ( ! isset($workplace['id']) || (int) $workplace['id'] != $workplace_id ) continue;

            $location = $workplace['location'];

            break;
        }

        return $location;
    }

    private function getBasicAllowance( $age ) : float
    {
        $rate = 0;

        // Based on the given table above
        foreach( $this->table['basic'] as $age_group => $value )
            if ( $age >= $age_group ) $rate = $value;

        return (float) $rate;
    }

    private function getTravelAllowance( string $point_a, string $point_b ) : float
    {
        $rate = 0;

        if (! empty( $point_a ) && ! empty( $point_b ) ) {
            // Strip out the braces from the coordinates.
            $P = preg_replace( '/[( )]/', '', $point_a );
            $Q = preg_replace( '/[( )]/', '', $point_b );

            @[ $x1, $y1 ] = explode( ',', $P );
            @[ $x2, $y2 ] = explode( ',', $Q );

            if ( $x1 && $x2 && $y1 && $y2 ) {
                // Calculate distance using the supplied formular
                // d = √((x2-x1)2 + (y2-y1)2) in kms

                // Distance between two coordinates.
                $distance = sqrt( pow($x2 -$x1, 2) + pow($y2 -$y1, 2) );

                if ( $distance >= 5 ) $rate = $distance * $this->table['travel'];
            }
        }

        return (float) $rate;
    }

    private function getMealAllowance() : float
    {
        return (float) $this->table['meal'];
    }

}

?>
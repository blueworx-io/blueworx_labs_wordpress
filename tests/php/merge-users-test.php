<?php
/**
 * Combining LearnDash progress when two accounts are merged.
 *
 * Run with: php tests/php/merge-users-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins, then the engine under test.
require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/includes/merge-users/merge.php';

/**
 * One course's progress record, the shape LearnDash stores.
 *
 * @param int $completed Steps done.
 * @param int $total     Steps in the course.
 * @return array Progress.
 */
function progress( $completed, $total ) {
	return array(
		'lessons'   => array(),
		'topics'    => array(),
		'completed' => $completed,
		'total'     => $total,
	);
}

echo "Course progress\n";

$merged = blueworx_merge_users_course_progress( array( 1 => progress( 1, 4 ) ), array( 2 => progress( 1, 2 ) ) );
check( 'a course only the spare had is added', array_keys( $merged ), array( 1, 2 ) );

$merged = blueworx_merge_users_course_progress( array( 1 => progress( 1, 4 ) ), array( 1 => progress( 3, 4 ) ) );
check( 'the spare wins where it was further along', $merged[1]['completed'], 3 );

$merged = blueworx_merge_users_course_progress( array( 1 => progress( 3, 4 ) ), array( 1 => progress( 1, 4 ) ) );
check( 'the kept account wins where it was further along', $merged[1]['completed'], 3 );

$merged = blueworx_merge_users_course_progress( array( 1 => progress( 3, 5 ) ), array( 1 => progress( 2, 2 ) ) );
check( 'a finished course beats an unfinished one with more steps done', $merged[1]['completed'], 2 );

$kept           = progress( 2, 4 );
$kept['marker'] = 'kept';
$merged         = blueworx_merge_users_course_progress( array( 1 => $kept ), array( 1 => progress( 2, 4 ) ) );
check( 'the kept account wins a tie', $merged[1]['marker'] ?? null, 'kept' );

check( 'nothing on either side is nothing', blueworx_merge_users_course_progress( array(), array() ), array() );
check( 'a stored non-array is treated as nothing', array_keys( blueworx_merge_users_course_progress( '', array( 5 => progress( 1, 1 ) ) ) ), array( 5 ) );

echo "\nQuiz attempts\n";

$quizzes = blueworx_merge_users_quizzes(
	array(
		array(
			'quiz' => 7,
			'time' => 100,
		),
	),
	array(
		array(
			'quiz' => 7,
			'time' => 100,
		),
		array(
			'quiz' => 8,
			'time' => 200,
		),
	)
);
check(
	'the kept attempts come first, then the spare\'s, without repeats',
	$quizzes,
	array(
		array(
			'quiz' => 7,
			'time' => 100,
		),
		array(
			'quiz' => 8,
			'time' => 200,
		),
	)
);

finish();

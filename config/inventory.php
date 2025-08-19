<?php

return [
	'abc_thresholds' => [
		'a' => env('ABC_THRESHOLD_A', 0.80),
		'b' => env('ABC_THRESHOLD_B', 0.95),
	],

	// average (moving average stock cost) | unit (static part unit_cost)
	'abc_cost_basis' => env('ABC_COST_BASIS', 'average'),
];



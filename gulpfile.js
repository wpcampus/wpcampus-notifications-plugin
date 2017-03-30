var gulp = require('gulp');
var phpcs = require('gulp-phpcs');
var sort = require('gulp-sort');
var watch = require('gulp-watch');
var wp_pot = require('gulp-wp-pot');

// Set the source for specific files.
var src = {
	php: ['**/*.php','!vendor/**','!node_modules/**']
};

// Check our PHP
gulp.task('php',function() {
	gulp.src(src.php)
		.pipe(phpcs({
			bin: 'vendor/bin/phpcs',
			standard: 'WordPress-Core'
		}))
		.pipe(phpcs.reporter('log'));
});

// Watch the files.
gulp.task('watch',function() {
	gulp.watch(src.php,['php']);
});

// Create the translation file.
gulp.task('translate', function() {
	gulp.src(src.php)
		.pipe(sort())
		.pipe(wp_pot({
			domain: 'wpc-notifications',
			destFile:'wpc-notifications.pot',
			package: 'WPCampus_Notifications',
			bugReport: 'https://github.com/wpcampus/wpcampus-notifications-plugin/issues',
			lastTranslator: 'WPCampus <code@wpcampus.org>',
			team: 'WPCampus <code@wpcampus.org>',
			headers: false
		}))
		.pipe(gulp.dest('languages/wpc-notifications.pot'));
});

// Test our files.
gulp.task('test',['php']);

// Our default tasks
gulp.task('default',['test','translate']);
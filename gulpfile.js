var autoprefixer = require('gulp-autoprefixer');
var gulp = require('gulp');
var minify = require('gulp-minify');
var phpcs = require('gulp-phpcs');
var rename = require('gulp-rename');
var sass = require('gulp-sass');
var sort = require('gulp-sort');
var watch = require('gulp-watch');
var wp_pot = require('gulp-wp-pot');

// Set the source for specific files.
var src = {
	scss: ['assets/scss/**/*'],
	js: ['assets/js/**/*','!assets/js/*.min.js'],
	php: ['**/*.php','!vendor/**','!node_modules/**']
};

// Define the destination paths for each file type.
var dest = {
	scss: 'assets/css',
	js: 'assets/js'
};

// Sass is pretty awesome, right?
gulp.task('sass',function() {
	return gulp.src(src.scss)
		.pipe(sass({
			outputStyle: 'compressed'
		})
			.on('error',sass.logError))
		.pipe(autoprefixer({
			browsers: ['last 2 versions'],
			cascade: false
		}))
		.pipe(rename({
			suffix: '.min'
		}))
		.pipe(gulp.dest(dest.scss));
});

// Minify our JS
gulp.task('js',function() {
	gulp.src(src.js)
		.pipe(minify({
			mangle: false,
			ext:{
				min:'.min.js'
			}
		}))
		.pipe(gulp.dest(dest.js))
});

// Check our PHP.
gulp.task('php',function() {
	gulp.src(src.php)
		.pipe(phpcs({
			bin: 'vendor/bin/phpcs',
			standard: 'WordPress-Core'
		}))
		.pipe(phpcs.reporter('log'));
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

// Compile all the things
gulp.task('compile',['sass','js']);

// Test our files.
gulp.task('test',['php']);

// Watch the files.
gulp.task('watch',function() {
	gulp.watch(src.scss,['sass']);
	gulp.watch(src.js,['js']);
	gulp.watch(src.php,['php']);
});

// Our default tasks.
gulp.task('default',['compile','test','translate']);
const autoprefixer = require('gulp-autoprefixer');
const cleanCSS = require('gulp-clean-css');
const gulp = require('gulp');
const mergeMediaQueries = require('gulp-merge-media-queries');
const minify = require('gulp-minify');
const notify = require('gulp-notify');
const rename = require('gulp-rename');
const sass = require('gulp-sass');
const shell = require('gulp-shell');
const sort = require('gulp-sort');
const wp_pot = require('gulp-wp-pot');

// Set the source for specific files.
const src = {
	sass: ['assets/src/scss/**/*'],
	js: ['assets/src/js/**/*'],
	php: ['**/*.php','!vendor/**','!node_modules/**']
};

// Define the destination paths for each file type.
const dest = {
	sass: 'assets/build/css',
	js: 'assets/build/js'
};

// Sass is pretty awesome, right?
gulp.task('sass',function() {
	return gulp.src(src.sass)
		.pipe(sass({
			outputStyle: 'expanded'
		}).on('error', sass.logError))
		.pipe(mergeMediaQueries())
		.pipe(autoprefixer({
			browsers: ['last 2 versions'],
			cascade: false
		}))
		.pipe(cleanCSS({
			compatibility: 'ie8'
		}))
		.pipe(rename({
			suffix: '.min'
		}))
		.pipe(gulp.dest(dest.sass))
		.pipe(notify('WPC Notifications SASS compiled'), {
			onLast: true,
			emitError: true
		});
});

// Minify our JS
gulp.task('js',function() {
	gulp.src(src.js)
		.pipe(minify({
			mangle: false,
			noSource: true,
			ext:{
				min:'.min.js'
			}
		}))
		.pipe(gulp.dest(dest.js))
		.pipe(notify('WPC Notifications JS compiled'), {
			onLast: true,
			emitError: true
		});
});

// "Sniff" our PHP.
gulp.task('php', function() {
	// TODO: Clean up. Want to run command and show notify for sniff errors.
	return gulp.src('wpcampus-notifications.php', {read: false})
		.pipe(shell(['composer sniff'], {
			ignoreErrors: true,
			verbose: false
		}))
		.pipe(notify('WPC Notifications PHP sniffed'), {
			onLast: true,
			emitError: true
		});
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
		.pipe(gulp.dest('languages/wpc-notifications.pot'))
		.pipe(notify('WPC Notifications translated'), {
			onLast: true,
			emitError: true
		});
});

// Compile all the things
gulp.task('compile',['sass','js']);

// Test our files.
gulp.task('test',['php']);

// Watch the files.
gulp.task('watch',['compile','php'],function() {
	gulp.watch(src.sass,['sass']);
	gulp.watch(src.js,['js']);
	gulp.watch(src.php,['php']);
});

// Our default tasks.
gulp.task('default',['compile','test','translate']);
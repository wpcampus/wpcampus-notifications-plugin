const autoprefixer = require( 'gulp-autoprefixer' );
const cleanCSS = require( 'gulp-clean-css' );
const gulp = require( 'gulp' );
const mergeMediaQueries = require( 'gulp-merge-media-queries' );
const minify = require( 'gulp-minify' );
const notify = require( 'gulp-notify' );
const rename = require( 'gulp-rename' );
const sass = require( 'gulp-sass' );

// Set the source for specific files.
const src = {
    sass: [ 'assets/css/src/**/*' ],
    js: [ 'assets/js/src/**/*' ]
};

// Define the destination paths for each file type.
const dest = {
    sass: 'assets/css',
    js: 'assets/js'
};

// Sass is pretty awesome, right?
gulp.task( 'sass', function( done ) {
    return gulp.src( src.sass )
        .pipe( sass( {
            outputStyle: 'expanded'
        } ).on( 'error', sass.logError ) )
        .pipe( mergeMediaQueries() )
        .pipe( autoprefixer( {
            cascade: false
        } ) )
        .pipe( cleanCSS( {
            compatibility: 'ie8'
        } ) )
        .pipe( rename( {
            suffix: '.min'
        } ) )
        .pipe( gulp.dest( dest.sass ) )
        .pipe( notify( 'WPC Notifications SASS compiled' ), {
            onLast: true,
            emitError: true
        } )
        .on( 'end', done );
} );

// Minify our JS
gulp.task( 'js', function( done ) {
    return gulp.src( src.js )
        .pipe( minify( {
            mangle: false,
            noSource: true,
            ext: {
                min: '.min.js'
            }
        } ) )
        .pipe( gulp.dest( dest.js ) )
        .pipe( notify( 'WPC Notifications JS compiled' ), {
            onLast: true,
            emitError: true
        } )
        .on( 'end', done );
} );

// Compile all the things
gulp.task( 'compile', gulp.series( 'sass', 'js' ) );

// Watch the files.
gulp.task( 'watch', gulp.series( 'compile', function( done ) {
    gulp.watch( src.sass, gulp.series( 'sass' ) );
    gulp.watch( src.js, gulp.series( 'js' ) );
    return done();
} ) );

// Our default tasks.
gulp.task( 'default', gulp.series( 'compile' ) );
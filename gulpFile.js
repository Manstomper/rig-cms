/*
 *
 * gulpFile for rigCMS
 *
 */

var gulp = require('gulp');
var cleanCSS = require('gulp-clean-css');
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');

var theme = 'manstomper';

gulp.task('admin-css', function() {

	var stylesheets = [
		'public_html/assets/font-awesome/css/font-awesome.css',
		'public_html/assets/bootstrap/css/bootstrap.css',
		'public_html/assets/admin.css',
	];

	return gulp.src(stylesheets)
		.pipe(cleanCSS())
		.pipe(concat('admin.min.css', { newLine: '\r\n' }))
		.pipe(gulp.dest('public_html/assets/'));
});

gulp.task('admin-js', function() {

	var scripts = [
		'public_html/assets/jquery/jquery-2.2.3.min.js',
		'public_html/assets/bootstrap/js/bootstrap.js'
	];

	return gulp.src(scripts)
		.pipe(uglify())
		.pipe(concat('admin.min.js', { newLine: '\r\n' }))
		.pipe(gulp.dest('public_html/assets/'));
});

gulp.task('theme-css', function() {

	var stylesheets = [
		'public_html/themes/' + theme + '/assets/bootstrap-grid.min.css',
		'public_html/themes/' + theme + '/assets/styles.css'
	];

	return gulp.src(stylesheets)
		.pipe(cleanCSS())
		.pipe(concat('styles.min.css', { newLine: '\r\n' }))
		.pipe(gulp.dest('public_html/themes/' + theme + '/assets/'));
});

gulp.task('theme-js', function() {

	var scripts = [
		'public_html/themes/' + theme + '/assets/prettify.min.js',
		'public_html/themes/' + theme + '/assets/scripts.js'
	];

	return gulp.src(scripts)
		.pipe(uglify())
		.pipe(concat('scripts.min.js', { newLine: '\r\n' }))
		.pipe(gulp.dest('public_html/themes/' + theme + '/assets/'));
});

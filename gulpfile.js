'use strict';

const { src, dest, parallel } = require('gulp');
const sass = require('gulp-dart-sass');
const rename = require('gulp-rename');
const merge = require('merge-stream');
const uglify = require('gulp-uglify');

// Style.
function plugincss() {
	return src('./assets/scss/style.scss')
		.pipe(sass({outputStyle: 'compressed'}))
		.pipe(rename({suffix: '.min'}))
        .pipe(dest('./assets/css/'));
}

// Admin Style.
function admincss() {
	return src('./assets/scss/admin.scss')
		.pipe(sass({outputStyle: 'compressed'}))
		.pipe(rename({suffix: '.min'}))
		.pipe(dest('./assets/css/'));
}

// JavaScript.
function pluginjs() {
	return src('./assets/js/main.js')
		.pipe(uglify())
		.pipe(rename({suffix: '.min'}))
		.pipe(dest('./assets/js/'));
}

exports.default = parallel(plugincss, admincss, pluginjs);

const gulp = require('gulp');
const svgSprite = require('gulp-svg-sprite');
const plumber = require('gulp-plumber');
const cheerio = require('gulp-cheerio');

const config = {
  dest: 'sprite',
  log: 'debug',
  shape: { // SVG shape related options
    id: { // SVG shape ID related options
      separator: '--', // Separator for directory name traversal
      pseudo: '~' // File name separator for shape states (e.g. ':hover')
    },
    dimension: {// Dimension related options
      maxWidth: 64, // Max. shape width
      maxHeight: 64, // Max. shape height
      precision: 0, // Floating point precision
      attributes: false, // Width and height attributes on embedded shapes
    },
    spacing: { // Spacing related options
      padding: 0, // Padding around all shapes
      box: 'content' // Padding strategy (similar to CSS `box-sizing`)
    },
    transform: ['svgo'], // List of transformations / optimizations
    meta: null, // Path to YAML file with meta / accessibility data
    align: null, // Path to YAML file with extended alignment data
    dest: 'optimized' // Output directory for optimized intermediate SVG shapes
  },
  svg: { // General options for created SVG files
    xmlDeclaration: true, // Add XML declaration to SVG sprite
    doctypeDeclaration: true, // Add DOCTYPE declaration to SVG sprite
    namespaceIDs: true, // Add namespace token to all IDs in SVG shapes
    namespaceClassnames: false, // Add namespace token to all CSS class names in SVG shapes
    dimensionAttributes: false // Width and height attributes on the sprite
  },
  mode: {
    symbol: true // Activate the «symbol» mode
  },
  variables: {}
};


const sprites = () => {
  return gulp.src('./src/*.svg')
    .pipe(plumber())
    .pipe(cheerio(($, file) => {
      const $svg = $('svg');
      $svg.removeAttr('inkscape:output_extension');
      $svg.removeAttr('inkscape:export-filename');
      $svg.removeAttr('inkscape:export-xdpi');
      $svg.removeAttr('inkscape:export-ydpi');
      $svg.addClass('svg-icon');
      $svg.addClass(`icon-${file.stem}`);
      const $path = $('path');
      $path.removeAttr('style');
      $path.addClass('svg-icon-graphic');
    }))
    .pipe(svgSprite(config))
    .on('error', (err) => console.log(err))
    .pipe(gulp.dest('out'));
};


const copy = () => {
  return gulp.src('./out/symbol/svg/*.svg')
    .pipe(gulp.dest('../css/icons/'));
};

exports.sprites = sprites;
exports.copy = copy;
exports.default = gulp.series(sprites, copy);

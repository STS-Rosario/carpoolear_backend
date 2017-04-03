/*******************************************************************************
 * Description:
 * 
 *   Gulp file to push changes to remote servers (eg: staging/production)
 *
 * Usage:
 * 
 *   gulp deploy --target --user=usuario
 * 
 *   --testing -> only view changes not apply
 *
 * Examples:
 * 
 *   gulp deploy --production --user=usuario  // push to production
 *   gulp deploy --develop    --user=usuario  // push to staging
 * 
 * Install:
 *    npm intall gulp -g 
 *    npm install 
 *   
 ******************************************************************************/
var gulp    = require('gulp'); 
var gutil   = require('gulp-util'); 
var argv    = require('minimist')(process.argv); 
var rsync   = require('gulp-rsync'); 
var prompt  = require('gulp-prompt'); 
var gulpif  = require('gulp-if');
var path    = require('path');

gulp.task('deploy', function() {
  
  // Dirs and Files to sync
  rsyncPaths = [ 'app' , 'config' , 'database' , 'public' , 'resources' , 'bootstrap' , 'cert' ];
  
  // Default options for rsync
  rsyncConf = {
    progress: true,
    incremental: true,
    relative: true,
    emptyDirectories: true,
    recursive: true,
    clean: false,
    exclude: [],
    dryrun: argv.testing
  };
  
  // develop
  if (argv.develop) {  
    
    rsyncConf.hostname = '138.197.64.208'; // hostname
    rsyncConf.username = argv.user || 'movilizame' ; // ssh username
    rsyncConf.destination = '/home/movilizame/sites/carpoolear_dev/'; // path where uploaded files go
    
  // Production
  } else if (argv.production) {

    rsyncConf.hostname = '138.197.64.208'; // hostname
    rsyncConf.username = argv.user || 'movilizame'; // ssh username
    rsyncConf.destination = '/home/movilizame/sites/carpoolear_dev/'; // path where uploaded files go
    
  
  // Missing/Invalid Target  
  } else {
    throwError('deploy', gutil.colors.red('Missing or invalid target'));
  }
  

  // Use gulp-rsync to sync the files 
  return gulp.src(rsyncPaths)
  .pipe(gulpif(
      argv.production, 
      prompt.confirm({
        message: 'Heads Up! Are you SURE you want to push to PRODUCTION?',
        default: false
      })
  ))
  .pipe(rsync(rsyncConf));

});


function throwError(taskName, msg) {
  throw new gutil.PluginError({
      plugin: taskName,
      message: msg
    });
}

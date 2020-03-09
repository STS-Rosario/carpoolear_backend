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
var exec = require('gulp-exec');
var isWin = /^win/.test(process.platform);

var reportOptions = {
  err: true, // default = true, false means don't write err 
  stderr: true, // default = true, false means don't write stderr 
  stdout: true // default = true, false means don't write stdout 
};

gulp.task('deploy', ['deploy-file'] ,function() {
  gulp.src(['.env.example'])
  .pipe(exec("ssh movilizame@104.131.15.228 -p 2200 'cd  /home/movilizame/sites/carpoolear_dev && ./after_deploy.sh'"))
  .pipe(exec.reporter(reportOptions));
});

gulp.task('deploy-file', function() {
  
  // Dirs and Files to sync
  rsyncPaths = ['artisan', 'after_deploy.sh', 'composer.json', 'composer.lock',  'app' , 'config' , 'database' , 'public' , 'resources' , 'bootstrap' , 'cert', 'tests', 'routes', 'storage/banks', 'storage/cc', 'storage/geojson' ];
  
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
  
  if (isWin) {
    rsyncConf.chmod = "ugo=rwX";
  }

  // develop
  if (argv.develop) {  
    
    rsyncConf.port = 2200;
    rsyncConf.hostname = '104.131.15.228'; // hostname
    rsyncConf.username = argv.user || 'movilizame' ; // ssh username
    rsyncConf.destination = '/home/movilizame/sites/carpoolear_dev/'; // path where uploaded files go
    
  // Production
  } else if (argv.production) {

    rsyncConf.port = 2200;
    rsyncConf.hostname = '104.131.15.228'; // hostname
    rsyncConf.username = argv.user || 'movilizame'; // ssh username
    rsyncConf.destination = '/home/movilizame/sites/carpoolear_dev/'; // path where uploaded files go
    
  
  } else if (argv.apalancar) {

    rsyncConf.port = 2200;
    rsyncConf.hostname = '45.55.196.14'; // hostname
    rsyncConf.username = argv.user || 'movilizame'; // ssh username
    rsyncConf.destination = '/home/movilizame/sites/apalancar/'; // path where uploaded files go
    
  
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

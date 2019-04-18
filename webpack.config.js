var Encore = require('@symfony/webpack-encore');

Encore

    // Directory where compiled assets will be stored.
    .setOutputPath('./public/assets/')

    // Public URL path used by the web server to access the output path.
    .setPublicPath('/assets/')

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if you JavaScript imports CSS.
     */
    .addEntry('app', [
        './assets/vendor/jquery.i18n.dist.js',
        './assets/vendor/bootstrap-typeahead.min',
        './assets/js/application.js',
        './assets/js/dateLocales.js',
        './assets/js/default.js',
        './assets/js/eventedit.js',
        './assets/js/eventshow.js',
        './assets/js/eventdata.js',
        './assets/js/programs.js',
        './assets/css/_mixins.scss',
        './assets/css/application.scss',
        './assets/css/default.scss',
        './assets/css/events.scss',
        './assets/css/programs.scss',
        './vendor/wikimedia/toolforge-bundle/Resources/assets/toolforge.js',
        './public/images/logo.svg',
        './public/favicon.ico',
    ])

    // Other options.
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSingleRuntimeChunk()
;

module.exports = Encore.getWebpackConfig();

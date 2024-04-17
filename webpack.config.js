const Encore = require( '@symfony/webpack-encore' );

Encore

	// Directory where compiled assets will be stored.
	.setOutputPath('./public/build/')

	// Public URL path used by the web server to access the output path.
	.setPublicPath('/build')

	// this is now needed so that your manifest.json keys are still `build/foo.js`
	// (which is a file that's used by Symfony's `asset()` function)
	.setManifestKeyPrefix('build')

	.copyFiles( {
		from: './assets/images',
		to: 'images/[path][name].[ext]'
	} )

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
		'./assets/js/application.js',
		'./assets/js/dateLocales.js',
		'./assets/js/default.js',
		'./assets/js/eventedit.js',
		'./assets/js/eventshow.js',
		'./assets/js/eventdata.js',
		'./assets/js/programs.js',
		'./assets/styles/_mixins.scss',
		'./assets/styles/application.scss',
		'./assets/styles/default.scss',
		'./assets/styles/events.scss',
		'./assets/styles/programs.scss',
		'./vendor/wikimedia/toolforge-bundle/Resources/assets/toolforge.js',
	] )

	// Other options.
	.autoProvidejQuery()
	.enableSassLoader()
	.cleanupOutputBeforeBuild()
	.disableSingleRuntimeChunk()
	.enableSourceMaps(!Encore.isProduction())
	.enableVersioning(Encore.isProduction())
;

module.exports = Encore.getWebpackConfig();

const path = require('path');

module.exports = {
	entry: './src/js/public.js',
	output: {
		filename: 'public.bundle.js',
		path: path.resolve(__dirname, 'assets/js'),
	},
	mode: 'production',
	externals: {
		jquery: 'jQuery',
	},
	module: {
		rules: [
			{
				test: /\.css$/i,
				use: [ 'style-loader', 'css-loader' ],
			},
		],
	},
};

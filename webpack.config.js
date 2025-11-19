// webpack.config.js
const path = require('path');
const WebpackBar = require('webpackbar');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

const isProd = process.env.NODE_ENV === 'production';

module.exports = {
  entry: './assets/js/main.js',
  output: {
    filename: path.posix.join('js', 'script.min.js'),
    path: path.resolve(__dirname, 'dist'),
    clean: true
  },
  module: {
    rules: [
      {
        test: /\.s[ac]ss$/i,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              url: false,
              sourceMap: isProd // Change to !isProd to disable sourceMap in Prod
            }
          },
          {
            loader: 'sass-loader',
            options: {
              implementation: require('sass-embedded'),
              sassOptions: {
                quietDeps: true
              },
              sourceMap: isProd // Change to !isProd to disable sourceMap in Prod
            }
          }
        ],
      },
      {
        test: /\.js$/,
        include: path.resolve(__dirname, 'assets/js'),
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            cacheDirectory: true
          }
        },
      },
    ],
  },
  plugins: [
    new WebpackBar(),
    new MiniCssExtractPlugin({
      filename: 'css/style.min.css'
    }),
  ],
  optimization: {
    minimize: isProd,
    minimizer: [
      new TerserPlugin({
        extractComments: false
      }),
      new CssMinimizerPlugin()
    ],
  },
  devtool: isProd ? 'source-map' : 'cheap-module-source-map',
  mode: isProd ? 'production' : 'development',
  stats: {
    assets: true,
    modules: true,
    entrypoints: true,
    colors: true,
    reasons: true,
    errorDetails: true,
  }
};

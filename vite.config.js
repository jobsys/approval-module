import { defineConfig } from "vite"
import laravel from "laravel-vite-plugin"

const dotenvExpand = require("dotenv-expand")
dotenvExpand(require("dotenv").config({ path: "../../.env" /* , debug: true */ }))

export default defineConfig({
	build: {
		outDir: "../../public/build-approval",
		emptyOutDir: true,
		manifest: true,
	},
	plugins: [
		laravel({
			publicDirectory: "../../public",
			buildDirectory: "build-approval",
			input: [`${__dirname}/Resources/assets/sass/app.scss`, `${__dirname}/Resources/assets/js/app.js`],
			refresh: true,
		}),
	],
})

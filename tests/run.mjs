import {createRequire} from 'node:module';
import {pathToFileURL,fileURLToPath} from 'node:url';
import fs from 'node:fs';
import path from 'node:path';
if (!process.env.PHP_WASM_PACKAGE_JSON) throw Error('Set PHP_WASM_PACKAGE_JSON to the temporary package.json');
const require=createRequire(process.env.PHP_WASM_PACKAGE_JSON);
const {PHP,loadPHPRuntime}=await import(pathToFileURL(require.resolve('@php-wasm/universal')));
const version=process.argv[2]||'8.3';
const loader=await import(pathToFileURL(require.resolve('@php-wasm/node-'+version.replace('.','-'))));
const php=new PHP(await loadPHPRuntime(await loader.getPHPLoaderModule(),{phpWasmAsyncMode:await loader.jspi()?'jspi':'asyncify'}));
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
function copy(src,dst){php.mkdirTree(dst);for(const e of fs.readdirSync(src,{withFileTypes:true})){if(e.name.startsWith('.'))continue;const a=path.join(src,e.name),b=dst+'/'+e.name;if(e.isDirectory())copy(a,b);else if(e.name.endsWith('.php'))php.writeFile(b,fs.readFileSync(a));}}
copy(root,'/plugin');
const result=await php.run({scriptPath:process.argv[3]==='lint'?'/plugin/tests/lint.php':'/plugin/tests/broadcast-regression.php'});
console.log(result.text);if(result.errors)console.error(result.errors);php.exit();process.exitCode=result.exitCode;

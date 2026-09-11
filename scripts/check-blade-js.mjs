#!/usr/bin/env node
/**
 * Parses the JavaScript inside every Blade view and fails if any block is
 * invalid, plus flags files containing invalid UTF-8.
 *
 * A single corrupted character once split a string literal across two lines in
 * admin/dashboard.blade.php. That is a syntax error, so the browser discarded
 * the entire <script> block and every handler on the page — notifications,
 * order modals, the message thread — silently stopped working. PHP lint and the
 * test suite both passed. Nothing caught it. This does.
 *
 * Usage: node scripts/check-blade-js.mjs [viewsDir]
 */
import { readFileSync, readdirSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { join, relative, extname } from 'node:path';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';

const viewsDir = process.argv[2] ?? 'resources/views';
const scratch = mkdtempSync(join(tmpdir(), 'blade-js-'));

/** Blade directives that wrap JS statements; dropping the line keeps it parseable. */
const DIRECTIVE = /^\s*@(if|elseif|else|endif|foreach|endforeach|forelse|empty|endforelse|php|endphp|isset|endisset|can|endcan|cannot|endcannot|for|endfor|while|endwhile|auth|endauth|guest|endguest|unless|endunless|push|endpush|section|endsection|once|endonce|error|enderror)\b/;

function bladeFiles(dir) {
  const found = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) found.push(...bladeFiles(path));
    else if (entry.name.endsWith('.blade.php')) found.push(path);
  }
  return found;
}

/** Replace server-rendered Blade with a literal so the result is parseable JS. */
function stripBlade(source) {
  return source
    .replace(/\{!!([\s\S]*?)!!\}/g, '1')
    .replace(/\{\{([\s\S]*?)\}\}/g, '1')
    .replace(/@json\((?:[^()]|\([^()]*\))*\)/g, '1')
    .split('\n')
    .filter((line) => !DIRECTIVE.test(line))
    .join('\n');
}

let failures = 0;
let blocks = 0;
const files = bladeFiles(viewsDir);

for (const file of files) {
  const raw = readFileSync(file);

  if (new TextDecoder('utf-8').decode(raw).includes('�')) {
    const upTo = new TextDecoder('utf-8').decode(raw).indexOf('�');
    const line = new TextDecoder('utf-8').decode(raw).slice(0, upTo).split('\n').length;
    console.error(`\n  ${relative('.', file)}:${line}\n    invalid UTF-8 byte — a multi-byte character is corrupted`);
    failures++;
    continue;
  }

  const source = raw.toString('utf8');
  const pattern = /<script(?![^>]*\bsrc=)([^>]*)>([\s\S]*?)<\/script>/g;
  let match;
  let index = 0;

  while ((match = pattern.exec(source))) {
    // Skip templates and JSON payloads — they are data, not scripts.
    if (/type=["'](?!text\/javascript|module|application\/javascript)/.test(match[1])) continue;

    index++;
    blocks++;
    const startLine = source.slice(0, match.index).split('\n').length;
    const candidate = join(scratch, 'block.js');
    writeFileSync(candidate, stripBlade(match[2]));

    try {
      execFileSync(process.execPath, ['--check', candidate], { stdio: 'pipe' });
    } catch (error) {
      failures++;
      const detail = error.stderr.toString().split('\n').slice(1, 4).join('\n');
      console.error(`\n  ${relative('.', file)} — <script> block #${index} near line ${startLine}\n${detail}`);
    }
  }
}

rmSync(scratch, { recursive: true, force: true });

if (failures > 0) {
  console.error(`\n${failures} problem(s) across ${blocks} script block(s) in ${files.length} views.\n`);
  process.exit(1);
}

console.log(`Blade JS OK — ${blocks} script blocks in ${files.length} views.`);

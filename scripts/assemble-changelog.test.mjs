import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseFragment, assemble } from './assemble-changelog.mjs';

const HEAD = `# Changelog

Preamble line.

## [1.0.0] - 2026-01-01

### Added
- The first thing.
`;

test('parseFragment: reads a single category block', () => {
  const blocks = parseFragment('### Added\n- A thing.\n', 'a.md');
  assert.deepEqual(blocks, [{ category: 'Added', body: '- A thing.' }]);
});

test('parseFragment: reads several category blocks in one fragment', () => {
  const blocks = parseFragment('### Added\n- A.\n\n### Fixed\n- B.\n', 'a.md');
  assert.deepEqual(blocks, [
    { category: 'Added', body: '- A.' },
    { category: 'Fixed', body: '- B.' },
  ]);
});

test('parseFragment: rejects a fragment with no recognised heading', () => {
  assert.throws(() => parseFragment('- just a bullet\n', 'bad.md'), /bad\.md/);
  assert.throws(() => parseFragment('### Nonsense\n- x\n', 'bad.md'), /bad\.md/);
});

test('assemble: inserts a new version section above the existing ones', () => {
  const out = assemble({
    changelog: HEAD,
    fragments: [{ name: 'a.md', text: '### Added\n- A new thing.\n' }],
    version: '1.1.0',
    date: '2026-08-01',
  });
  assert.match(out, /^# Changelog\n\nPreamble line\.\n/);
  assert.ok(out.indexOf('## [1.1.0] - 2026-08-01') < out.indexOf('## [1.0.0] - 2026-01-01'));
  assert.match(out, /## \[1\.1\.0\] - 2026-08-01\n\n### Added\n- A new thing\./);
});

test('assemble: merges fragments by category, in canonical category order', () => {
  const out = assemble({
    changelog: HEAD,
    fragments: [
      { name: 'b.md', text: '### Fixed\n- Fix two.\n' },
      { name: 'a.md', text: '### Fixed\n- Fix one.\n\n### Added\n- Add one.\n' },
    ],
    version: '1.1.0',
    date: '2026-08-01',
  });
  const section = out.slice(out.indexOf('## [1.1.0]'), out.indexOf('## [1.0.0]'));
  assert.ok(section.indexOf('### Added') < section.indexOf('### Fixed'));
  // Within a category, fragments concatenate in filename order: a.md then b.md.
  assert.ok(section.indexOf('- Fix one.') < section.indexOf('- Fix two.'));
  assert.equal(section.match(/### Fixed/g).length, 1);
});

test('assemble: appends into an existing heading for the same version', () => {
  const out = assemble({
    changelog: HEAD,
    fragments: [{ name: 'a.md', text: '### Fixed\n- Late fix.\n' }],
    version: '1.0.0',
    date: '2026-08-01',
  });
  assert.equal(out.match(/## \[1\.0\.0\]/g).length, 1);
  assert.match(out, /### Added\n- The first thing\.\n\n### Fixed\n- Late fix\./);
});

test('assemble: appending keeps a blank line before the next version heading', () => {
  const twoVersions = `${HEAD}\n## [0.9.0] - 2025-12-01\n\n### Fixed\n- An old fix.\n`;
  const out = assemble({
    changelog: twoVersions,
    fragments: [{ name: 'a.md', text: '### Fixed\n- Late fix.\n' }],
    version: '1.0.0',
    date: '2026-08-01',
  });
  assert.match(out, /- Late fix\.\n\n## \[0\.9\.0\]/);
});

test('assemble: no fragments leaves the changelog untouched', () => {
  assert.equal(assemble({ changelog: HEAD, fragments: [], version: '1.1.0', date: '2026-08-01' }), HEAD);
});

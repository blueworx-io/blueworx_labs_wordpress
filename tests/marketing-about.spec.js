// About page marketing content (Task 3 of Plan 2). Logged-out by definition,
// so every navigation goes through cacheBust() — see tests/public-site.spec.js's
// top-of-file note on Cloudways Varnish caching stale logged-out responses.
import { expect } from '@playwright/test';
import { test, isPlaceholder, cacheBust } from './helpers.js';

test.describe('Marketing about page', () => {
  test.skip(isPlaceholder, 'No real WordPress target configured.');

  test('renders the hero, Why BlueWorx, stats band, team and story sections', async ({ page }) => {
    await page.goto(cacheBust('/about'));

    // Hero: the tech-hero part in centered mode.
    const hero = page.locator('main > div > section.tech-hero');
    await expect(hero).toHaveCount(1);
    await expect(hero.locator('.tech-badge')).toContainText('About BlueWorx');
    await expect(hero.locator('h1.h1')).toContainText('Works Like a Partner');
    await expect(hero.locator('h1.h1 .tech-grad')).toHaveText('Works Like a Partner');

    // Why BlueWorx: copy column + four plain svc-card parts.
    const why = page.locator('main > div .about-why');
    await expect(why).toHaveCount(1);
    await expect(why.locator('.btn', { hasText: 'Book a Call' })).toHaveAttribute('href', /\/contact\/?$/);
    await expect(why.locator('.btn', { hasText: 'View Our Work' })).toHaveAttribute('href', /\/work\/?$/);
    const whyCards = why.locator('.why-grid > div.svc');
    await expect(whyCards).toHaveCount(4);
    await expect(whyCards.nth(0)).toContainText('One team, end to end');

    // Stats band: four stats, first one carries the star rating.
    const statsBand = page.locator('main > div > section.stats-band');
    await expect(statsBand).toHaveCount(1);
    const stats = statsBand.locator('.stat');
    await expect(stats).toHaveCount(4);
    await expect(stats.nth(0)).toContainText('5.0');
    await expect(stats.nth(0).locator('svg')).toHaveCount(1);
    await expect(stats.nth(1)).toContainText('82+');
    await expect(stats.nth(1)).toContainText('Projects Completed');
    await expect(stats.nth(2)).toContainText('100k +');
    await expect(stats.nth(3)).toContainText('2K +');

    // Our Team: three team cards.
    const teamCards = page.locator('main > div .team-grid > .team-card');
    await expect(teamCards).toHaveCount(3);
    await expect(teamCards.nth(0).locator('.team-mono')).toHaveText('R');
    await expect(teamCards.nth(0).locator('h4')).toHaveText('Ross');
    await expect(teamCards.nth(0).locator('p')).toHaveText('Project Manager');

    // Client Success Stories: three linked work-card parts.
    const storyCards = page.locator('main > div .work-grid > a.work-card');
    await expect(storyCards).toHaveCount(3);
    await expect(storyCards.nth(0)).toContainText('Hirasté');
    await expect(storyCards.nth(0)).toHaveAttribute('href', /\/work\/?$/);
    await expect(page.locator('main > div .btn', { hasText: 'View All Work' })).toHaveAttribute('href', /\/work\/?$/);
  });
});

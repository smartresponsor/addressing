# Cloudflare Pages setup for Addressing

Goal
- Auto deploy docs on push to master
- Preview deployments on PRs

Recommended mode: Git integration (Cloudflare builds from GitHub)

Steps (Cloudflare Dashboard)
1) Pages -> Create a project -> Connect to Git
2) Pick this GitHub repository
3) Production branch: master
4) Build settings:
   - Build command: `pip install -r docs/requirements.txt && mkdocs build --strict`
   - Build output directory: `site`
5) Save and deploy

Notes
- Keep secrets in Cloudflare/GitHub, not in repo.

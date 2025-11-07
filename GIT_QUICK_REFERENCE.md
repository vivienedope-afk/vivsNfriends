# 🎯 Quick Reference - Git Commands

## 📥 Getting Started

```bash
# Clone repository (one time only)
git clone https://github.com/vivienedope-afk/vivsNfriends.git
cd vivsNfriends
```

---

## 📊 Check Status

```bash
# Check current branch
git branch

# Check what files changed
git status

# See commit history
git log --oneline
```

---

## 🌿 Branch Management

```bash
# Create new branch from main
git checkout -b feature/your-feature-name

# Switch to existing branch
git checkout branch-name

# List all branches
git branch -a

# Delete local branch
git branch -d branch-name
```

---

## 🔄 Update Your Code

```bash
# Update main branch
git checkout main
git pull origin main

# Update your feature branch with latest main
git checkout feature/your-branch
git merge main
```

---

## 💾 Save Your Work

```bash
# Add all changed files
git add .

# Add specific file
git add filename.php

# Commit with message
git commit -m "Add: Your clear description"

# Push to GitHub
git push origin feature/your-branch-name
```

---

## 🚫 Undo Changes

```bash
# Discard changes in file (before add)
git checkout -- filename.php

# Unstage file (after add, before commit)
git reset filename.php

# Undo last commit (keep changes)
git reset --soft HEAD~1

# Discard all local changes (DANGER!)
git reset --hard HEAD
```

---

## 🔍 View Differences

```bash
# See changes in files (before commit)
git diff

# See changes for specific file
git diff filename.php

# Compare branches
git diff main..feature/your-branch
```

---

## 🤝 Working with Remote

```bash
# See remote repositories
git remote -v

# Fetch updates (don't merge yet)
git fetch origin

# Pull = Fetch + Merge
git pull origin main
```

---

## 📦 Common Workflows

### Daily Start
```bash
git checkout main
git pull origin main
git checkout feature/your-branch
git merge main
# Start coding...
```

### Save Progress
```bash
git add .
git commit -m "Add: Feature description"
git push origin feature/your-branch
```

### Submit for Review
```bash
git push origin feature/your-branch
# Then go to GitHub and create Pull Request
```

---

## ⚡ Useful Aliases (Optional)

Add to your `.gitconfig` or run:

```bash
git config --global alias.st status
git config --global alias.co checkout
git config --global alias.br branch
git config --global alias.cm commit
git config --global alias.lg "log --oneline --graph --all"
```

Then use shortcuts:
```bash
git st      # instead of git status
git co main # instead of git checkout main
git br      # instead of git branch
git cm -m "message" # instead of git commit -m "message"
git lg      # pretty log
```

---

## 🆘 Emergency Commands

### Made mistake in last commit message?
```bash
git commit --amend -m "New correct message"
git push origin branch-name --force
```

### Need to stash work temporarily?
```bash
# Save work without committing
git stash

# Do something else...

# Get work back
git stash pop
```

### Accidentally committed to wrong branch?
```bash
# Copy commit hash (git log)
git checkout correct-branch
git cherry-pick <commit-hash>
git checkout wrong-branch
git reset --hard HEAD~1
```

---

## 📱 Mobile-Friendly Cheat Sheet

**Clone**: `git clone <url>`
**Status**: `git status`
**Branch**: `git checkout -b feature/name`
**Add**: `git add .`
**Commit**: `git commit -m "message"`
**Push**: `git push origin branch-name`
**Update**: `git pull origin main`
**Merge**: `git merge main`

---

## 💡 Pro Tips

1. **Commit often**: Small, focused commits are better than large ones
2. **Clear messages**: Future you will thank present you
3. **Pull before push**: Always update before pushing
4. **Test before commit**: Don't commit broken code
5. **One feature per branch**: Keep branches focused

---

## 🎨 Commit Message Format

```
Type: Short description (max 50 chars)

Longer explanation if needed (wrap at 72 chars)
- Bullet points for details
- What changed and why

Closes #123 (if related to issue)
```

**Types**:
- `Add:` New feature
- `Fix:` Bug fix
- `Update:` Modify existing
- `Remove:` Delete code/files
- `Refactor:` Code improvement
- `Docs:` Documentation only
- `Style:` Formatting, no code change
- `Test:` Add/update tests

**Examples**:
```bash
git commit -m "Add: Payment validation in resident account"
git commit -m "Fix: Incorrect calculation in monthly dues"
git commit -m "Update: Dashboard stat card colors to gold theme"
git commit -m "Refactor: Extract duplicate login logic to helper"
```

---

## 🚨 What NOT to Do

❌ `git add .` without checking `git status` first
❌ Commit passwords or sensitive data
❌ `git push --force` to main branch
❌ Work directly on main branch
❌ Commit without testing
❌ Use generic messages like "fix" or "update"
❌ Commit large binary files (images > 1MB)

✅ **Instead**: Review changes, use .gitignore, create feature branches, test first, write clear messages

---

**Keep this handy! Bookmark this file! 📌**

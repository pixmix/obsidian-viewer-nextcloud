/**
 * Obsidian-compatible search expression parser and evaluator.
 *
 * Grammar:
 *   expr     → or_expr
 *   or_expr  → and_expr ('OR' and_expr)*
 *   and_expr → unary+
 *   unary    → '-' unary | primary
 *   primary  → '(' expr ')' | OPERATOR primary | PROPERTY | REGEX | QUOTED | WORD
 *
 * Operators: file: path: content: tag: line: section: block: task: task-todo: task-done:
 * Properties: [name] [name:value] ["name":value] [name:null]
 * Regex: /pattern/
 */

// --- Tokenizer ---

const OPERATORS = ['task-todo:', 'task-done:', 'content:', 'section:', 'file:', 'path:', 'line:', 'block:', 'task:', 'tag:']

function tokenize(input) {
	const tokens = []
	let i = 0
	const len = input.length

	while (i < len) {
		if (input[i] === ' ' || input[i] === '\t') { i++; continue }

		if (input[i] === '"') {
			let j = i + 1, str = ''
			while (j < len && input[j] !== '"') {
				if (input[j] === '\\' && j + 1 < len) { str += input[j + 1]; j += 2 }
				else { str += input[j]; j++ }
			}
			tokens.push({ t: 'Q', v: str })
			i = j + 1
			continue
		}

		if (input[i] === '/' && i < len - 1) {
			let j = i + 1, pat = ''
			while (j < len && input[j] !== '/') {
				if (input[j] === '\\' && j + 1 < len) { pat += input[j] + input[j + 1]; j += 2 }
				else { pat += input[j]; j++ }
			}
			if (j < len && j > i + 1) {
				tokens.push({ t: 'R', v: pat })
				i = j + 1
				continue
			}
		}

		if (input[i] === '[') {
			let j = i + 1, depth = 1, s = ''
			while (j < len && depth > 0) {
				if (input[j] === '[') depth++
				else if (input[j] === ']') { depth--; if (depth === 0) break }
				s += input[j]; j++
			}
			let pn, pv
			if (s.startsWith('"')) {
				const eq = s.indexOf('"', 1)
				pn = s.substring(1, eq)
				const rest = s.substring(eq + 1).trim()
				pv = rest.startsWith(':') ? rest.substring(1).trim() : undefined
			} else {
				const ci = s.indexOf(':')
				if (ci >= 0) { pn = s.substring(0, ci).trim(); pv = s.substring(ci + 1).trim() }
				else pn = s.trim()
			}
			tokens.push({ t: 'P', n: pn, v: pv })
			i = j + 1
			continue
		}

		if (input[i] === '(') { tokens.push({ t: '(' }); i++; continue }
		if (input[i] === ')') { tokens.push({ t: ')' }); i++; continue }
		if (input[i] === '-') { tokens.push({ t: '-' }); i++; continue }

		let j = i
		while (j < len && input[j] !== ' ' && input[j] !== '\t' && input[j] !== '(' && input[j] !== ')' && input[j] !== '"') j++
		if (j === i) { i++; continue }
		const word = input.substring(i, j)

		let matched = false
		for (const op of OPERATORS) {
			if (word.length >= op.length && word.substring(0, op.length).toLowerCase() === op) {
				const rest = word.substring(op.length)
				if (rest.startsWith('-')) {
					tokens.push({ t: 'O', n: op.slice(0, -1), v: null })
					tokens.push({ t: '-' })
					if (rest.length > 1) tokens.push({ t: 'W', v: rest.substring(1) })
				} else {
					tokens.push({ t: 'O', n: op.slice(0, -1), v: rest || null })
				}
				matched = true
				break
			}
		}
		if (!matched) {
			if (word === 'OR') tokens.push({ t: 'OR' })
			else tokens.push({ t: 'W', v: word })
		}
		i = j
	}
	return tokens
}

// --- Parser ---

function parse(tokens) {
	let pos = 0
	const peek = () => pos < tokens.length ? tokens[pos] : null
	const advance = () => tokens[pos++]

	function expr() { return orExpr() }

	function orExpr() {
		let left = andExpr()
		while (peek()?.t === 'OR') {
			advance()
			left = { t: 'OR', l: left, r: andExpr() }
		}
		return left
	}

	function andExpr() {
		const terms = [unary()]
		while (peek() && peek().t !== 'OR' && peek().t !== ')') {
			terms.push(unary())
		}
		return terms.length === 1 ? terms[0] : { t: 'AND', c: terms }
	}

	function unary() {
		if (peek()?.t === '-') {
			advance()
			return { t: 'NOT', c: unary() }
		}
		return primary()
	}

	function primary() {
		const tok = peek()
		if (!tok) return { t: 'W', v: '' }

		if (tok.t === '(') {
			advance()
			const e = expr()
			if (peek()?.t === ')') advance()
			return e
		}

		if (tok.t === 'O') {
			advance()
			let child
			if (tok.v) {
				child = { t: 'W', v: tok.v }
			} else if (peek()?.t === '(') {
				advance()
				child = expr()
				if (peek()?.t === ')') advance()
			} else if (peek()?.t === 'Q') {
				child = advance()
			} else if (peek()?.t === 'R') {
				child = advance()
			} else if (peek() && peek().t !== 'OR' && peek().t !== ')') {
				child = unary()
			} else {
				child = { t: 'W', v: '' }
			}
			return { t: 'OP', n: tok.n, c: child }
		}

		if (tok.t === 'P' || tok.t === 'W' || tok.t === 'Q' || tok.t === 'R') {
			advance()
			return tok
		}

		advance()
		return { t: 'W', v: '' }
	}

	const result = expr()
	return result
}

// --- Helpers ---

function parseFrontmatter(content) {
	const m = content.match(/^---\s*\n([\s\S]*?)\n---/)
	if (!m) return {}
	const fm = {}
	for (const line of m[1].split('\n')) {
		const kv = line.match(/^([a-zA-Z_][\w-]*):\s*(.*)$/)
		if (kv) fm[kv[1].toLowerCase()] = kv[2]
	}
	return fm
}

function extractTags(content) {
	const tags = []
	const fm = parseFrontmatter(content)
	if (fm.tags) {
		const s = fm.tags.replace(/^\[|\]$/g, '')
		for (const t of s.split(',')) {
			const v = t.trim().replace(/^["']|["']$/g, '')
			if (v) tags.push(v)
		}
	}
	const cleaned = content.replace(/```[\s\S]*?```/g, '').replace(/`[^`]*`/g, '')
	const matches = cleaned.match(/(?:^|\s)#([a-zA-Z][\w/-]*)/g) || []
	for (const m of matches) tags.push(m.trim().replace(/^#/, ''))
	return [...new Set(tags)]
}

function contains(text, term) {
	return text.toLowerCase().includes(term.toLowerCase())
}

function splitSections(content) {
	const sections = []
	let cur = []
	for (const line of content.split('\n')) {
		if (/^#{1,6}\s+/.test(line)) {
			if (cur.length) sections.push(cur.join('\n'))
			cur = []
		}
		cur.push(line)
	}
	if (cur.length) sections.push(cur.join('\n'))
	return sections
}

function splitBlocks(content) {
	return content.split(/\n\s*\n/).filter((b) => b.trim())
}

// --- Evaluator ---

function evalText(node, text) {
	switch (node.t) {
	case 'W': return contains(text, node.v)
	case 'Q': return contains(text, node.v)
	case 'R': try { return new RegExp(node.v, 'i').test(text) } catch { return false }
	case 'AND': return node.c.every((ch) => evalText(ch, text))
	case 'OR': return evalText(node.l, text) || evalText(node.r, text)
	case 'NOT': return !evalText(node.c, text)
	default: return false
	}
}

function evaluate(node, file) {
	const content = file.content || ''

	switch (node.t) {
	case 'W': return contains(content, node.v)
	case 'Q': return contains(content, node.v)
	case 'R': try { return new RegExp(node.v, 'i').test(content) } catch { return false }
	case 'AND': return node.c.every((ch) => evaluate(ch, file))
	case 'OR': return evaluate(node.l, file) || evaluate(node.r, file)
	case 'NOT': return !evaluate(node.c, file)

	case 'OP': {
		const { n, c } = node
		switch (n) {
		case 'file': return evalText(c, file.name || '')
		case 'path': return evalText(c, file.path || file.id || '')
		case 'content': return evalText(c, content)
		case 'tag': {
			if (file.type === 'tag') return evalText(c, (file.name || '').replace(/^#/, ''))
			if (!content) return false
			return extractTags(content).some((tag) => evalText(c, tag))
		}
		case 'line': return content.split('\n').some((line) => evalText(c, line))
		case 'section': return splitSections(content).some((sec) => evalText(c, sec))
		case 'block': return splitBlocks(content).some((blk) => evalText(c, blk))
		case 'task': return content.split('\n').filter((l) => /^\s*- \[.\]/.test(l)).some((l) => evalText(c, l))
		case 'task-todo': return content.split('\n').filter((l) => /^\s*- \[ \]/.test(l)).some((l) => evalText(c, l))
		case 'task-done': return content.split('\n').filter((l) => /^\s*- \[x\]/i.test(l)).some((l) => evalText(c, l))
		default: return false
		}
	}

	case 'P': {
		if (!content) return false
		const fm = parseFrontmatter(content)
		const pn = node.n.toLowerCase()
		if (!(pn in fm)) return false
		if (node.v === undefined) return true
		if (node.v === 'null') return fm[pn].trim() === ''
		return contains(fm[pn], node.v)
	}

	default: return false
	}
}

// --- Public API ---

export function matchesQuery(file, query) {
	if (!query || !query.trim()) return false
	const tokens = tokenize(query)
	const ast = parse(tokens)
	return evaluate(ast, file)
}

export function collectTerms(query) {
	if (!query || !query.trim()) return []
	const tokens = tokenize(query)
	const terms = []
	function walk(node) {
		if (node.t === 'W' || node.t === 'Q') terms.push(node.v)
		if (node.c) Array.isArray(node.c) ? node.c.forEach(walk) : walk(node.c)
		if (node.l) walk(node.l)
		if (node.r) walk(node.r)
	}
	walk(parse(tokens))
	return terms
}

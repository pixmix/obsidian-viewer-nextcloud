export default function commentPlugin(md) {
	md.core.ruler.after('block', 'obsidian_comments', (state) => {
		for (const token of state.tokens) {
			if (token.type === 'inline' && token.content.includes('%%')) {
				token.content = token.content.replace(/%%[\s\S]*?%%/g, '')
				if (token.children) {
					token.children = token.children.filter((ch) => {
						if (ch.type === 'text') {
							ch.content = ch.content.replace(/%%[\s\S]*?%%/g, '')
						}
						return true
					})
				}
			}
		}
	})
}

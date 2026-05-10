<template>
	<div class="vault-search">
		<div class="vault-search__input-row">
			<MagnifyIcon :size="16" class="vault-search__icon" />
			<input ref="input"
				v-model="query"
				type="text"
				class="vault-search__input"
				placeholder="Search..."
				@input="onInput"
				@focus="showHints = !query"
				@blur="onBlur"
				@keydown.enter="doSearch" />
			<button v-if="query"
				class="vault-search__clear"
				@click="clearSearch">
				<CloseIcon :size="14" />
			</button>
		</div>

		<SearchHints v-if="showHints && !query"
			class="vault-search__hints"
			:properties="properties"
			@select="insertOperator" />

		<div v-if="searching" class="vault-search__loading">
			<NcLoadingIcon :size="20" />
		</div>

		<div v-else-if="results.length > 0" class="vault-search__results">
			<div v-for="result in results"
				:key="result.path"
				class="vault-search__result"
				@click="$emit('select', result.path)">
				<div class="vault-search__result-name">{{ result.name }}</div>
				<div v-if="result.matches && result.matches.length"
					class="vault-search__result-matches">
					<div v-for="(m, i) in result.matches"
						:key="i"
						class="vault-search__match-line">
						{{ m.text }}
					</div>
				</div>
				<div class="vault-search__result-path">{{ result.path }}</div>
			</div>
		</div>

		<div v-else-if="searched && !searching" class="vault-search__empty">
			No results found.
		</div>
	</div>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import SearchHints from './SearchHints.vue'

import { fetchSearch, fetchProperties } from '../services/api.js'

export default {
	name: 'VaultSearch',

	components: { NcLoadingIcon, MagnifyIcon, CloseIcon, SearchHints },

	props: {
		vaultName: { type: String, required: true },
	},

	emits: ['select'],

	data() {
		return {
			query: '',
			results: [],
			searching: false,
			searched: false,
			showHints: false,
			properties: [],
			debounceTimer: null,
		}
	},

	watch: {
		vaultName: {
			immediate: true,
			handler() {
				this.loadProperties()
			},
		},
	},

	mounted() {
		this.$nextTick(() => this.$refs.input?.focus())
	},

	beforeDestroy() {
		if (this.debounceTimer) clearTimeout(this.debounceTimer)
	},

	methods: {
		async loadProperties() {
			try {
				this.properties = await fetchProperties(this.vaultName)
			} catch {
				this.properties = []
			}
		},

		onInput() {
			this.showHints = false
			if (this.debounceTimer) clearTimeout(this.debounceTimer)
			if (!this.query.trim()) {
				this.results = []
				this.searched = false
				return
			}
			this.debounceTimer = setTimeout(() => this.doSearch(), 300)
		},

		onBlur() {
			setTimeout(() => { this.showHints = false }, 200)
		},

		async doSearch() {
			const q = this.query.trim()
			if (!q) return
			this.showHints = false
			this.searching = true
			try {
				this.results = await fetchSearch(this.vaultName, q)
			} catch (e) {
				this.results = []
			} finally {
				this.searching = false
				this.searched = true
			}
		},

		clearSearch() {
			this.query = ''
			this.results = []
			this.searched = false
			this.$refs.input?.focus()
		},

		insertOperator(prefix) {
			this.query = prefix
			this.showHints = false
			this.$nextTick(() => this.$refs.input?.focus())
		},
	},
}
</script>

<style scoped>
.vault-search {
	padding: 8px;
	height: 100%;
	display: flex;
	flex-direction: column;
}

.vault-search__input-row {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 6px 10px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: var(--color-background-dark);
}

.vault-search__input-row:focus-within {
	border-color: var(--color-primary-element);
}

.vault-search__icon {
	opacity: 0.5;
	flex-shrink: 0;
}

.vault-search__input {
	flex: 1;
	border: none;
	background: transparent;
	outline: none;
	font-size: 13px;
	color: var(--color-main-text);
	min-width: 0;
}

.vault-search__clear {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	border: none;
	background: transparent;
	cursor: pointer;
	opacity: 0.5;
	color: var(--color-main-text);
	border-radius: 50%;
	flex-shrink: 0;
}

.vault-search__clear:hover {
	opacity: 1;
	background: var(--color-background-hover);
}

.vault-search__hints {
	margin-top: 8px;
}

.vault-search__loading {
	display: flex;
	justify-content: center;
	padding: 20px;
}

.vault-search__results {
	margin-top: 8px;
	flex: 1;
	overflow-y: auto;
}

.vault-search__result {
	padding: 8px 10px;
	cursor: pointer;
	border-radius: 6px;
}

.vault-search__result:hover {
	background: var(--color-background-hover);
}

.vault-search__result-name {
	font-weight: 600;
	font-size: 13px;
}

.vault-search__result-matches {
	margin-top: 4px;
}

.vault-search__match-line {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	padding: 1px 0;
}

.vault-search__result-path {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	opacity: 0.7;
	margin-top: 2px;
}

.vault-search__empty {
	padding: 20px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>

<template>
	<div class="quick-switcher-overlay" @click.self="$emit('close')">
		<div class="quick-switcher">
			<input ref="input"
				v-model="query"
				class="quick-switcher__input"
				type="text"
				placeholder="Search files..."
				@keydown.down.prevent="moveDown"
				@keydown.up.prevent="moveUp"
				@keydown.enter.prevent="selectCurrent"
				@keydown.esc="$emit('close')" />
			<ul v-if="filtered.length > 0" ref="list" class="quick-switcher__list">
				<li v-for="(file, i) in filtered"
					:key="file.path"
					class="quick-switcher__item"
					:class="{ 'quick-switcher__item--active': i === activeIndex }"
					@click="select(file)"
					@mouseenter="activeIndex = i">
					<span class="quick-switcher__name">{{ file.name }}</span>
					<span class="quick-switcher__dir">{{ file.dir }}</span>
				</li>
			</ul>
			<div v-else class="quick-switcher__empty">
				No matching files
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'QuickSwitcher',

	props: {
		files: {
			type: Array,
			required: true,
		},
	},

	emits: ['select', 'close'],

	data() {
		return {
			query: '',
			activeIndex: 0,
		}
	},

	computed: {
		fileList() {
			return this.files.map((path) => ({
				path,
				name: path.substring(path.lastIndexOf('/') + 1).replace(/\.(md|canvas)$/, ''),
				dir: path.substring(0, path.lastIndexOf('/')),
			}))
		},

		filtered() {
			if (!this.query) return this.fileList.slice(0, 50)
			const q = this.query.toLowerCase()
			return this.fileList
				.filter((f) => f.name.toLowerCase().includes(q) || f.path.toLowerCase().includes(q))
				.slice(0, 50)
		},
	},

	watch: {
		query() {
			this.activeIndex = 0
		},
	},

	mounted() {
		this.$nextTick(() => {
			if (this.$refs.input) this.$refs.input.focus()
		})
	},

	methods: {
		moveDown() {
			if (this.activeIndex < this.filtered.length - 1) {
				this.activeIndex++
				this.scrollToActive()
			}
		},

		moveUp() {
			if (this.activeIndex > 0) {
				this.activeIndex--
				this.scrollToActive()
			}
		},

		scrollToActive() {
			this.$nextTick(() => {
				if (!this.$refs.list) return
				const item = this.$refs.list.children[this.activeIndex]
				if (item) item.scrollIntoView({ block: 'nearest' })
			})
		},

		selectCurrent() {
			if (this.filtered.length > 0) {
				this.select(this.filtered[this.activeIndex])
			}
		},

		select(file) {
			this.$emit('select', file.path)
		},
	},
}
</script>

<style scoped>
.quick-switcher-overlay {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.3);
	display: flex;
	align-items: flex-start;
	justify-content: center;
	padding-top: 20vh;
	z-index: 9999;
}

.quick-switcher {
	width: 500px;
	max-width: 90vw;
	background: var(--color-main-background);
	border-radius: 12px;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
	overflow: hidden;
}

.quick-switcher__input {
	width: 100%;
	padding: 12px 16px;
	border: none;
	border-bottom: 1px solid var(--color-border);
	font-size: 16px;
	outline: none;
	background: transparent;
	color: var(--color-main-text);
	box-sizing: border-box;
}

.quick-switcher__list {
	list-style: none;
	margin: 0;
	padding: 4px 0;
	max-height: 400px;
	overflow-y: auto;
}

.quick-switcher__item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 16px;
	cursor: pointer;
}

.quick-switcher__item--active {
	background: var(--color-background-hover);
}

.quick-switcher__name {
	font-weight: 500;
}

.quick-switcher__dir {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	margin-left: 12px;
	text-align: right;
	flex-shrink: 0;
}

.quick-switcher__empty {
	padding: 20px 16px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}
</style>

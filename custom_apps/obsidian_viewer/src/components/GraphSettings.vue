<template>
	<div class="graph-settings" @pointerup="onSliderUp">
		<div v-if="sliderTip.show"
			class="graph-settings__slider-tip"
			:style="{ left: sliderTip.x + 'px', top: sliderTip.y + 'px' }">
			{{ sliderTip.value }}
		</div>
		<div class="graph-settings__header">
			<button class="graph-settings__header-btn" title="Reset" @click="$emit('reset')">
				<RefreshIcon :size="18" />
			</button>
			<button class="graph-settings__header-btn" title="Close" @click="$emit('close')">
				<CloseIcon :size="18" />
			</button>
		</div>

		<div class="graph-settings__section">
			<h4 class="graph-settings__section-title" @click="sections.filters = !sections.filters">
				<span>Filters</span>
				<ChevronIcon :size="16" :class="{ 'chevron-closed': !sections.filters }" />
			</h4>
			<div v-show="sections.filters" class="graph-settings__section-body graph-settings__filter-search-wrapper">
				<input v-model="local.filters.search"
					type="text"
					class="graph-settings__search"
					placeholder="Search..."
					@input="emitUpdate"
					@focus="showFilterHints = true"
					@blur="onFilterBlur" />
				<SearchHints v-if="showFilterHints && !local.filters.search"
					class="graph-settings__filter-hints"
					:properties="properties"
					@select="insertFilterOperator" />
				<label class="graph-settings__toggle">
					<span>Tags</span>
					<input v-model="local.filters.tags" type="checkbox" @change="emitUpdate" />
				</label>
				<label class="graph-settings__toggle">
					<span>Attachments</span>
					<input v-model="local.filters.attachments" type="checkbox" @change="emitUpdate" />
				</label>
				<label class="graph-settings__toggle">
					<span>Existing files only</span>
					<input v-model="local.filters.existingOnly" type="checkbox" @change="emitUpdate" />
				</label>
				<label class="graph-settings__toggle">
					<span>Markdown links</span>
					<input v-model="local.filters.mdLinks" type="checkbox" @change="emitUpdate" />
				</label>
				<label class="graph-settings__toggle">
					<span>Orphans</span>
					<input v-model="local.filters.orphans" type="checkbox" @change="emitUpdate" />
				</label>
			</div>
		</div>

		<div class="graph-settings__section">
			<h4 class="graph-settings__section-title" @click="sections.groups = !sections.groups">
				<span>Groups</span>
				<ChevronIcon :size="16" :class="{ 'chevron-closed': !sections.groups }" />
			</h4>
			<div v-show="sections.groups" class="graph-settings__section-body">
				<div v-for="(group, i) in local.groups"
					:key="i"
					class="graph-settings__group-row-wrapper">
					<div class="graph-settings__group-row">
						<input v-model="group.query"
							type="text"
							class="graph-settings__group-input"
							placeholder="path: file: tag: or text"
							@input="emitUpdate"
							@focus="focusedGroup = i"
							@blur="onGroupBlur" />
						<input v-model="group.color"
							type="color"
							class="graph-settings__group-color"
							@input="emitUpdate" />
						<button class="graph-settings__group-delete" @click="removeGroup(i)">
							<CloseIcon :size="14" />
						</button>
					</div>
					<SearchHints v-if="focusedGroup === i && !group.query"
						class="graph-settings__group-hints-dropdown"
						:properties="properties"
						@select="(prefix) => insertGroupOperator(i, prefix)" />
				</div>
				<button class="graph-settings__new-group-btn" @click="addGroup">New group</button>
			</div>
		</div>

		<div class="graph-settings__section">
			<h4 class="graph-settings__section-title" @click="sections.display = !sections.display">
				<span>Display</span>
				<ChevronIcon :size="16" :class="{ 'chevron-closed': !sections.display }" />
			</h4>
			<div v-show="sections.display" class="graph-settings__section-body">
				<label class="graph-settings__toggle">
					<span>Arrows</span>
					<input v-model="local.display.arrows" type="checkbox" @change="emitUpdate" />
				</label>
				<div class="graph-settings__slider">
					<span>Text fade threshold</span>
					<input v-model.number="local.display.textFadeThreshold"
						type="range" min="0" max="5" step="0.1"
						@input="onSliderInput($event)"
						@pointerdown="onSliderDown($event)"
						@pointerenter="showTip($event)"
						@pointerleave="hideTip" />
				</div>
				<div class="graph-settings__slider">
					<span>Node size</span>
					<input v-model.number="local.display.nodeSize"
						type="range" min="1" max="15" step="0.5"
						@input="onSliderInput($event)"
						@pointerdown="onSliderDown($event)"
						@pointerenter="showTip($event)"
						@pointerleave="hideTip" />
				</div>
				<div class="graph-settings__slider">
					<span>Link thickness</span>
					<input v-model.number="local.display.linkThickness"
						type="range" min="0.5" max="5" step="0.25"
						@input="onSliderInput($event)"
						@pointerdown="onSliderDown($event)"
						@pointerenter="showTip($event)"
						@pointerleave="hideTip" />
				</div>
			</div>
		</div>

		<div class="graph-settings__section">
			<h4 class="graph-settings__section-title" @click="sections.forces = !sections.forces">
				<span>Forces</span>
				<ChevronIcon :size="16" :class="{ 'chevron-closed': !sections.forces }" />
			</h4>
			<div v-show="sections.forces" class="graph-settings__section-body">
				<div class="graph-settings__slider">
					<span>Centre force</span>
					<input v-model.number="local.forces.centerForce"
						type="range" min="0" max="1" step="0.01"
						@input="onSliderInput($event)"
						@pointerdown="onSliderDown($event)"
						@pointerenter="showTip($event)"
						@pointerleave="hideTip" />
				</div>
				<div class="graph-settings__slider">
					<span>Repel force</span>
					<input v-model.number="local.forces.repelForce"
						type="range" min="0" max="20" step="0.5"
						@input="onSliderInput($event)"
						@pointerdown="onSliderDown($event)"
						@pointerenter="showTip($event)"
						@pointerleave="hideTip" />
				</div>
				<div class="graph-settings__slider">
					<span>Link force</span>
					<input v-model.number="local.forces.linkForce"
						type="range" min="0" max="1" step="0.01"
						@input="onSliderInput($event)"
						@pointerdown="onSliderDown($event)"
						@pointerenter="showTip($event)"
						@pointerleave="hideTip" />
				</div>
				<div class="graph-settings__slider">
					<span>Link distance</span>
					<input v-model.number="local.forces.linkDistance"
						type="range" min="0" max="500" step="5"
						@input="onSliderInput($event)"
						@pointerdown="onSliderDown($event)"
						@pointerenter="showTip($event)"
						@pointerleave="hideTip" />
				</div>
			</div>
		</div>

		<div class="graph-settings__animate">
			<button class="graph-settings__animate-btn" @click="$emit('animate')">
				<AutoFixIcon :size="18" />
				<span>Animate</span>
			</button>
		</div>
	</div>
</template>

<script>
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ChevronIcon from 'vue-material-design-icons/ChevronDown.vue'
import AutoFixIcon from 'vue-material-design-icons/AutoFix.vue'
import SearchHints from './SearchHints.vue'

export default {
	name: 'GraphSettings',

	components: {
		RefreshIcon,
		CloseIcon,
		ChevronIcon,
		AutoFixIcon,
		SearchHints,
	},

	props: {
		settings: {
			type: Object,
			required: true,
		},
		properties: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['update', 'close', 'reset', 'animate'],

	data() {
		return {
			local: JSON.parse(JSON.stringify(this.settings)),
			sections: {
				filters: true,
				groups: true,
				display: true,
				forces: true,
			},
			showFilterHints: false,
			focusedGroup: null,
			sliderTip: { show: false, value: '', x: 0, y: 0 },
			isDragging: false,
		}
	},

	watch: {
		settings: {
			deep: true,
			handler(newVal) {
				this.local = JSON.parse(JSON.stringify(newVal))
			},
		},
	},

	methods: {
		emitUpdate() {
			this.$emit('update', JSON.parse(JSON.stringify(this.local)))
		},

		updateTipPosition(input) {
			const rect = input.getBoundingClientRect()
			const min = parseFloat(input.min)
			const max = parseFloat(input.max)
			const val = parseFloat(input.value)
			const ratio = (val - min) / (max - min)
			const thumbHalf = 8
			const trackWidth = rect.width - thumbHalf * 2
			this.sliderTip = {
				show: true,
				value: val,
				x: rect.left + thumbHalf + ratio * trackWidth,
				y: rect.top - 4,
			}
		},

		onSliderInput(e) {
			this.updateTipPosition(e.target)
			this.emitUpdate()
		},

		onSliderDown(e) {
			this.isDragging = true
			this.updateTipPosition(e.target)
		},

		onSliderUp() {
			if (!this.isDragging) return
			this.isDragging = false
			this.sliderTip.show = false
		},

		showTip(e) {
			this.updateTipPosition(e.target)
		},

		hideTip() {
			if (!this.isDragging) this.sliderTip.show = false
		},

		addGroup() {
			this.local.groups.push({ query: '', color: '#7c3aed' })
			this.emitUpdate()
		},

		removeGroup(index) {
			this.local.groups.splice(index, 1)
			this.emitUpdate()
		},

		onGroupBlur() {
			setTimeout(() => { this.focusedGroup = null }, 150)
		},

		insertGroupOperator(index, prefix) {
			this.local.groups[index].query = prefix
			this.focusedGroup = null
			this.emitUpdate()
		},

		onFilterBlur() {
			setTimeout(() => { this.showFilterHints = false }, 150)
		},

		insertFilterOperator(prefix) {
			this.local.filters.search = prefix
			this.showFilterHints = false
			this.emitUpdate()
		},
	},
}
</script>

<style scoped>
.graph-settings {
	position: absolute;
	top: 8px;
	right: 8px;
	width: 260px;
	max-height: calc(100% - 16px);
	overflow-y: auto;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 10px;
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
	z-index: 10;
	padding: 8px 0;
	font-size: 13px;
}

.graph-settings__header {
	display: flex;
	justify-content: flex-end;
	gap: 2px;
	padding: 0 8px 4px;
	border-bottom: 1px solid var(--color-border);
}

.graph-settings__header-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border: none;
	border-radius: 6px;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	opacity: 0.6;
}

.graph-settings__header-btn:hover {
	background: var(--color-background-hover);
	opacity: 1;
}

.graph-settings__section {
	border-bottom: 1px solid var(--color-border);
}

.graph-settings__section:last-of-type {
	border-bottom: none;
}

.graph-settings__section-title {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin: 0;
	padding: 8px 12px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	user-select: none;
}

.graph-settings__section-title:hover {
	color: var(--color-main-text);
}

.graph-settings__section-title .material-design-icon {
	transition: transform 0.2s;
}

.chevron-closed {
	transform: rotate(-90deg);
}

.graph-settings__section-body {
	padding: 0 12px 8px;
}

.graph-settings__search {
	width: 100%;
	padding: 6px 10px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	font-size: 13px;
	outline: none;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	box-sizing: border-box;
	margin-bottom: 6px;
}

.graph-settings__search:focus {
	border-color: var(--color-primary-element);
}

.graph-settings__toggle {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 4px 0;
	cursor: pointer;
}

.graph-settings__toggle input[type="checkbox"] {
	-webkit-appearance: none;
	appearance: none;
	width: 34px;
	min-width: 34px;
	height: 18px;
	background: var(--color-background-dark);
	border: 1px solid var(--color-border-dark);
	border-radius: 9px;
	position: relative;
	cursor: pointer;
	transition: background 0.2s, border-color 0.2s;
}

.graph-settings__toggle input[type="checkbox"]::after {
	content: '';
	position: absolute;
	width: 14px;
	height: 14px;
	border-radius: 50%;
	background: white;
	top: 1px;
	left: 1px;
	transition: transform 0.2s;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.graph-settings__toggle input[type="checkbox"]:checked {
	background: var(--color-primary-element);
	border-color: var(--color-primary-element);
}

.graph-settings__toggle input[type="checkbox"]:checked::after {
	transform: translateX(16px);
}

.graph-settings__slider {
	padding: 4px 0;
}

.graph-settings__slider span {
	display: block;
	margin-bottom: 2px;
}

.graph-settings__slider input[type="range"] {
	width: 100%;
	accent-color: var(--color-primary-element);
	height: 4px;
}

.graph-settings__group-row-wrapper {
	position: relative;
	margin-bottom: 4px;
}

.graph-settings__group-row {
	display: flex;
	align-items: center;
	gap: 4px;
}

.graph-settings__group-hints-dropdown {
	position: absolute;
	top: 100%;
	left: 0;
	right: 32px;
	z-index: 20;
	margin-top: 2px;
}

.graph-settings__filter-search-wrapper {
	position: relative;
}

.graph-settings__filter-hints {
	position: absolute;
	top: 36px;
	left: 0;
	right: 0;
	z-index: 20;
}

.graph-settings__group-input {
	flex: 1;
	min-width: 0;
	padding: 5px 8px;
	border: 1px solid var(--color-border);
	border-radius: 4px;
	font-size: 12px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	outline: none;
}

.graph-settings__group-input:focus {
	border-color: var(--color-primary-element);
}

.graph-settings__group-color {
	width: 26px;
	height: 26px;
	min-width: 26px;
	border: 2px solid var(--color-border);
	border-radius: 50%;
	cursor: pointer;
	padding: 0;
	background: none;
	-webkit-appearance: none;
	appearance: none;
}

.graph-settings__group-color::-webkit-color-swatch-wrapper {
	padding: 0;
}

.graph-settings__group-color::-webkit-color-swatch {
	border: none;
	border-radius: 50%;
}

.graph-settings__group-color::-moz-color-swatch {
	border: none;
	border-radius: 50%;
}

.graph-settings__group-delete {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	min-width: 22px;
	border: none;
	background: transparent;
	cursor: pointer;
	opacity: 0.4;
	color: var(--color-main-text);
	border-radius: 4px;
}

.graph-settings__group-delete:hover {
	opacity: 1;
	background: var(--color-background-hover);
}

.graph-settings__new-group-btn {
	width: 100%;
	padding: 6px 12px;
	border: 1px dashed var(--color-border);
	border-radius: 6px;
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	font-size: 13px;
}

.graph-settings__new-group-btn:hover {
	border-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.graph-settings__animate {
	padding: 8px 12px;
}

.graph-settings__animate-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	width: 100%;
	padding: 8px 12px;
	border: none;
	border-radius: 8px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
}

.graph-settings__animate-btn:hover {
	opacity: 0.9;
}

.graph-settings__slider-tip {
	position: fixed;
	transform: translate(-50%, -100%);
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 4px;
	padding: 1px 6px;
	font-size: 11px;
	pointer-events: none;
	z-index: 100;
	white-space: nowrap;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}
</style>

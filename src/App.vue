<template>
	<Content :class="{'icon-loading': loading, 'd-block': true }" app-name="vueexample">
		<div v-if="ageIsDisable">
			<input type="text"
				   v-model="age"
				   @focus="isFocused = true"
				   @blur="isFocused = false"
				   placeholder="number of days to delete(content)"
				   class="w-100" />
			<br>

			<button @click="sendFileAge"
					class="button bg-warning w-100">submit</button>
		</div>
		<div v-else>you cant access this section</div>

	</Content>
</template>

<script>
/* eslint-disable */
import Content from '@nextcloud/vue/dist/Components/Content'
import axios from '@nextcloud/axios'
import {generateUrl} from '@nextcloud/router'

export default {
	name: 'App',
	components: {
		Content,
	},
	data() {
		return {
			isFocused : false,
			ageIsDisable: false,
			loading: false,
			show: true,
			starred: false,
			age: '',
			fileInfo: '',
		}
	},
	methods: {
		update(fileInfo) {
			this.ageIsDisable = false
			this.fileInfo = fileInfo
			if (!fileInfo.shareOwner){
				this.ageIsDisable = true
			}
			console.log(fileInfo);
		},
		sendFileAge() {
			const url = generateUrl('/apps/fileage')
			console.log(this.fileInfo)
			const body = {fileInfo: this.fileInfo, age: this.age}
			axios.post(url, body)
				.then(function (response) {
					alert(response.data.result)
				})
				.catch(function (error) {
					alert("activity not found")
				});
		},
		getFileAge(){
			const url = generateUrl('/apps/fileage/get_fileage')
			console.log(this.fileInfo)
			const body = {fileInfo: this.fileInfo}
			let self = this;
			axios.post(url, body)
				.then(function (response) {
					self.age =response.data.result.expired_input;
					debugger
				})
				.catch(function (error) {
					alert("activity not found")
				});
		}
	},
	close() {
		this.show = false
		console.debug(arguments)
	},
	newButtonAction() {
		console.debug(arguments)
	},
	log() {
		console.debug(arguments)
	},
	updated () {
		if (!this.isFocused){
			this.getFileAge();
		}
	},
	mounted () {
		this.getFileAge();
	}
}
</script>
<style lang="scss" scoped>
.w-100{
	width: 100%;
}

.d-block{
	display: block;
}
</style>

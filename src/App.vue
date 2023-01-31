<template>
	<Content :class="{'icon-loading': loading, 'd-block': true }" app-name="vueexample">
		<div v-if="fileInfo.type == 'dir'">
			<div v-if="ageIsDisable">
				<input type="text"
					   v-model="age"
					   @focus="isFocused = true"
					   @blur="isFocused = false"
					   placeholder="cycle days to delete"
					   class="w-100" />
				<br>
				<button @click="sendFileAge"
						class="button bg-warning w-100" style="border-radius: 0; background-color: #30b6ff; color: white">submit</button>
			</div>
			<div v-else>you cant access this section</div>
		</div>
		<div v-else>
			<b>uploaded at :</b>
			<hr>
			<b>{{createdAtDateTime}}</b>
			<hr>
<!--			<b>{{createdAt}}</b>-->
		</div>


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
			createdAt: '',
		}
	},
	methods: {
		update(fileInfo) {
			this.ageIsDisable = false
			this.fileInfo = fileInfo
			console.log(this.fileInfo)
			debugger
			if (!fileInfo.shareOwner){
				this.ageIsDisable = true
			}
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
					self.createdAt =response.data.result.timestamp;
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
	computed:{
		createdAtDateTime(){
			return new Date(this.createdAt*1000 ).toString();
		}
	},
	mounted () {
		console.log(this.fileInfo.shareOwner)
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

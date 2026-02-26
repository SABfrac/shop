import axios from 'axios'



const http = axios.create({
    baseURL:  '/api', // напр. /api, если прокси
    timeout: 15000,
    withCredentials: true,
})




export default http
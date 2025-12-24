import axios from 'axios'



const http = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api', // напр. /api, если прокси
    timeout: 15000,
    withCredentials: true,
})




export default http
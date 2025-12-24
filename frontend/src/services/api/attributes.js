import http from './http'

export const getAttributes = () => http.get('/attributes')
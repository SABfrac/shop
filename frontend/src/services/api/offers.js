import http from './http'

export const getOffers = () => http.get(`/offers/${id}`)
export const createOffer = (data) => http.post('/offers', data);

export const updateOffer = (id, data) => http.patch(`/offers/${id}`, data);

export const deleteOffer = (id) => http.delete(`/offers/${id}`);

export const upsertOffers = async ({offers}) => {
    return http.post('/offers/save',  offers  )
}



